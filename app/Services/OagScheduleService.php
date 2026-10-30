<?php

namespace App\Services;

use App\Models\TeamFlight;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Looks up a flight's published schedule, plus its status (estimated/actual times, gate) once OAG
 * has one, via OAG's Flight Info API. Unlike the AI web-search fallback this is a commercial feed,
 * so a match is trusted the same way an AviationStack sync is (written straight to the TeamFlight).
 */
class OagScheduleService
{
    private const STATES = [
        'Scheduled' => 'scheduled',
        'OutGate' => 'active',
        'InFlight' => 'active',
        'Landed' => 'landed',
        'InGate' => 'landed',
        'Canceled' => 'cancelled',
        'Cancelled' => 'cancelled',
        'Diverted' => 'diverted',
    ];

    private string $baseUrl = 'https://api.oag.com/flight-instances/';

    public function __construct(private FlightMovementSync $movementSync)
    {
    }

    /**
     * Looks the flight up and saves its scheduled time and, once OAG has one, its status; movements
     * follow a time change through FlightMovementSync. Null when OAG has no matching flight.
     *
     * @return array{instance: array, status: array, saved: bool, change: ?array}|null
     */
    public function sync(TeamFlight $flight): ?array
    {
        $instance = $this->lookupSchedule($flight);
        if (!$instance) {
            return null;
        }

        $side = $flight->direction === 'arrival' ? 'arrival' : 'departure';
        $leg = $instance[$side] ?? [];
        $status = $this->status($instance);
        $live = $status[$side];
        $tracking = $status['flight_status'] ? [
            'flight_status' => $status['flight_status'],
            'actual_at' => $live['actual'] ? Carbon::parse($live['actual']) : null,
            'estimated_at' => $live['estimated'] ? Carbon::parse($live['estimated']) : null,
            'delay_minutes' => $live['delay'] !== null ? max(0, $live['delay']) : null,
        ] : [];

        $saved = !empty($leg['date']['local']) && !empty($leg['time']['local']);
        $change = null;
        if ($saved) {
            $scheduledAt = Carbon::parse("{$leg['date']['local']} {$leg['time']['local']}");
            $before = FlightMovementSync::before($flight);
            $change = DB::transaction(function () use ($flight, $scheduledAt, $before, $tracking) {
                $flight->update(['scheduled_at' => $scheduledAt, 'flight_synced_at' => now()] + $tracking);

                return FlightMovementSync::changed($flight, $before)
                    ? $this->movementSync->sync($flight->loadMissing('team:id,code'), $before, $flight->team?->code ?? '')
                    : null;
            });
        }

        return ['instance' => $instance, 'status' => $status, 'saved' => $saved, 'change' => $change];
    }

    /**
     * @return array|null the raw OAG flight-instance record, or null if not configured,
     *     the flight number couldn't be parsed, or nothing was found/the call failed.
     */
    public function lookupSchedule(TeamFlight $teamFlight): ?array
    {
        $key = config('services.oag.key');
        $date = $teamFlight->scheduled_at?->toDateString();

        if (!$key || !$teamFlight->flight_number || !$date) {
            return null;
        }

        $parsed = $this->parseFlightNumber($teamFlight->flight_number);
        if (!$parsed) {
            return null;
        }

        try {
            // OAG dates by departure; an overnight arrival left the day before it lands.
            $from = $teamFlight->direction === 'arrival' ? $teamFlight->scheduled_at->copy()->subDay()->toDateString() : $date;
            $response = Http::timeout(15)->withHeaders(['Subscription-Key' => $key])
                ->get($this->baseUrl, [
                    'CarrierCode' => $parsed['carrier'],
                    'FlightNumber' => $parsed['number'],
                    'DepartureDateTime' => "{$from}/{$date}",
                    'CodeType' => 'IATA',
                    'version' => 'v2',
                    'Content' => 'status',
                ]);

            if (!$response->successful()) {
                Log::warning('OagScheduleService: API error', [
                    'flight_id' => $teamFlight->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            // Only the instance whose own leg falls on the flight's day; prefer the leg at its airport.
            $side = $teamFlight->direction === 'arrival' ? 'arrival' : 'departure';
            $airport = ($side === 'arrival' ? $teamFlight->destinationAirport : $teamFlight->originAirport)?->code;
            $sameDay = collect($response->json('data') ?? [])
                ->filter(fn ($i) => ($i[$side]['date']['local'] ?? null) === $date);
            $instance = $sameDay->first(fn ($i) => $airport && strcasecmp($i[$side]['airport']['iata'] ?? '', $airport) === 0)
                ?? $sameDay->first();

            if (!$instance) {
                Log::info('OagScheduleService: no results', [
                    'flight_id' => $teamFlight->id,
                    'flight_number' => $teamFlight->flight_number,
                    'date' => $date,
                ]);

                return null;
            }

            return $instance;
        } catch (\Throwable $e) {
            Log::error('OagScheduleService: exception', [
                'flight_id' => $teamFlight->id,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * The instance's status per leg. Times are the airport's local wall clock ("Y-m-d\TH:i", offset
     * dropped); delay is minutes late against schedule (negative = early). All null before OAG has a status.
     *
     * @return array{flight_status: ?string, departure: array, arrival: array}
     */
    public function status(array $instance): array
    {
        $status = $instance['statusDetails'][0] ?? [];

        $leg = function (string $side, string $gate, string $ground) use ($status): array {
            $times = $status[$side] ?? [];
            $at = fn (string $kind) => self::wallClock($times[$kind][$gate]['local'] ?? $times[$kind][$ground]['local'] ?? null);

            return [
                'actual' => $at('actualTime'),
                'estimated' => $at('estimatedTime'),
                'gate' => ($times['gate'] ?? '') ?: null,
                'delay' => self::minutes($times['actualTime']["{$gate}Variation"] ?? $times['estimatedTime']["{$gate}Variation"] ?? null),
            ];
        };

        $state = $status['state'] ?? null;

        return [
            'flight_status' => $state ? (self::STATES[$state] ?? strtolower($state)) : null,
            'departure' => $leg('departure', 'outGate', 'offGround'),
            'arrival' => $leg('arrival', 'inGate', 'onGround'),
        ];
    }

    private static function wallClock(?string $local): ?string
    {
        return $local ? substr($local, 0, 16) : null;
    }

    private static function minutes(?string $variation): ?int
    {
        if (!$variation || !preg_match('/^(-)?(\d+):(\d{2})/', $variation, $m)) {
            return null;
        }

        return ($m[1] ? -1 : 1) * ((int) $m[2] * 60 + (int) $m[3]);
    }

    /**
     * Splits a stored flight number (e.g. "QR 162", "QR162", "QR1423") into
     * OAG's expected carrier code + numeric flight number.
     *
     * @return array{carrier: string, number: string}|null
     */
    private function parseFlightNumber(string $flightNumber): ?array
    {
        // IATA designators may hold a digit (J9, 6E, 9W); ICAO ones are three letters.
        if (!preg_match('/^\s*([A-Za-z]{3}|[A-Za-z][A-Za-z0-9]|[0-9][A-Za-z])\s*0*(\d{1,4})\s*$/', $flightNumber, $matches)) {
            return null;
        }

        return [
            'carrier' => strtoupper($matches[1]),
            'number' => $matches[2],
        ];
    }
}
