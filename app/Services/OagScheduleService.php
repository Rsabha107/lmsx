<?php

namespace App\Services;

use App\Models\TeamFlight;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Looks up a flight's published schedule (not live tracking) via OAG's Flight
 * Info API - the authoritative source AviationStack's live-only data can't
 * provide for flights months in the future. Unlike the AI web-search fallback,
 * this is a real commercial schedule database, so a match is trusted the same
 * way an AviationStack sync is (written straight to the TeamFlight record).
 */
class OagScheduleService
{
    private string $baseUrl = 'https://api.oag.com/flight-instances/';

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
            $response = Http::timeout(15)->withHeaders(['Subscription-Key' => $key])
                ->get($this->baseUrl, [
                    'CarrierCode' => $parsed['carrier'],
                    'FlightNumber' => $parsed['number'],
                    'DepartureDateTime' => $date,
                    'CodeType' => 'IATA',
                    'version' => 'v2',
                ]);

            if (!$response->successful()) {
                Log::warning('OagScheduleService: API error', [
                    'flight_id' => $teamFlight->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $instance = $response->json('data.0');

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
     * Splits a stored flight number (e.g. "QR 162", "QR162", "QR1423") into
     * OAG's expected carrier code + numeric flight number.
     *
     * @return array{carrier: string, number: string}|null
     */
    private function parseFlightNumber(string $flightNumber): ?array
    {
        if (!preg_match('/^\s*([A-Za-z]{2,3})\s*0*(\d+)\s*$/', $flightNumber, $matches)) {
            return null;
        }

        return [
            'carrier' => strtoupper($matches[1]),
            'number' => $matches[2],
        ];
    }
}
