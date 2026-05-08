<?php

namespace App\Services;

use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlightSyncService
{
    private string $apiKey = '';
    private string $baseUrl = 'https://api.aviationstack.com/v1';

    public function __construct()
    {
        $this->apiKey = (string) config('services.aviationstack.key', '');
    }

    public function syncAll(): array
    {
        $synced = 0;
        $failed = 0;

        Team::active()->whereNotNull('flight_number')->get()->each(function (Team $team) use (&$synced, &$failed) {
            $result = $this->syncTeam($team);
            $result ? $synced++ : $failed++;
        });

        return compact('synced', 'failed');
    }

    // Sync a single team: fetch from API, update DB only on exact date match, return data or null
    public function syncTeam(Team $team): ?array
    {
        $result = $this->fetchFlight($team);
        if (!$result) {
            return null;
        }

        // Do not persist data that belongs to a different flight date
        if (!empty($result['_date_mismatch'])) {
            Log::warning('FlightSyncService: date mismatch — skipping DB update', [
                'team'        => $team->code,
                'flight'      => $team->flight_number,
                'planned'     => $team->arrival_date_time?->toDateString(),
                'flight_date' => $result['flight_date'] ?? null,
            ]);
            return $result; // return data for display, but DB was NOT updated
        }

        $actualArrival   = $result['arrival']['actual']   ?? null;
        $actualDeparture = $result['departure']['actual'] ?? null;

        $team->update([
            'flight_status'              => $result['flight_status'] ?? null,
            'actual_arrival_date_time'   => $actualArrival   ? \Carbon\Carbon::parse($actualArrival)   : null,
            'actual_departure_date_time' => $actualDeparture ? \Carbon\Carbon::parse($actualDeparture) : null,
            'arrival_delay_minutes'      => $result['arrival']['delay']   ?? null,
            'departure_delay_minutes'    => $result['departure']['delay'] ?? null,
            'flight_synced_at'           => now(),
        ]);

        Log::info('FlightSyncService: synced', [
            'team'   => $team->code,
            'flight' => $team->flight_number,
            'status' => $result['flight_status'] ?? 'unknown',
        ]);

        return $result;
    }

    // Fetch raw AviationStack flight data for a team (no DB writes).
    // Sets _date_mismatch=true on the returned array when the API result is for a different date.
    public function fetchFlight(Team $team): ?array
    {
        if (!$team->flight_number) {
            return null;
        }

        if (!$this->apiKey) {
            Log::warning('FlightSyncService: AVIATIONSTACK_API_KEY not configured');
            return null;
        }

        try {
            $response = Http::timeout(15)->get("{$this->baseUrl}/flights", [
                'access_key'  => $this->apiKey,
                'flight_iata' => $team->flight_number,
            ]);

            if (!$response->successful()) {
                Log::warning('FlightSyncService: API error', [
                    'team'   => $team->code,
                    'flight' => $team->flight_number,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();

            if (empty($data['data'])) {
                Log::info('FlightSyncService: no results', [
                    'team'   => $team->code,
                    'flight' => $team->flight_number,
                ]);
                return null;
            }

            $targetDate = $team->arrival_date_time?->toDateString();

            // Try to find an exact date match first
            $matched = $targetDate
                ? collect($data['data'])->first(fn ($f) => ($f['flight_date'] ?? null) === $targetDate)
                : null;

            if ($matched) {
                return $matched;
            }

            // No date match — return the first result but flag it as a mismatch
            $fallback = $data['data'][0];
            $fallback['_date_mismatch']   = true;
            $fallback['_planned_date']    = $targetDate;
            return $fallback;
        } catch (\Throwable $e) {
            Log::error('FlightSyncService: exception', [
                'team'    => $team->code,
                'flight'  => $team->flight_number,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
