<?php

namespace App\Services;

use App\Models\TeamFlight;
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

    // Sync a TeamFlight record: fetch from API, update team_flights table, return raw data or null.
    public function syncFlightRecord(TeamFlight $teamFlight): ?array
    {
        if (!$teamFlight->flight_number || !$this->apiKey) {
            if (!$this->apiKey) Log::warning('FlightSyncService: AVIATIONSTACK_API_KEY not configured');
            return null;
        }

        try {
            $response = Http::timeout(15)->get("{$this->baseUrl}/flights", [
                'access_key'  => $this->apiKey,
                'flight_iata' => $teamFlight->flight_number,
            ]);

            if (!$response->successful()) {
                Log::warning('FlightSyncService: API error', ['flight_id' => $teamFlight->id, 'status' => $response->status()]);
                return null;
            }

            $data = $response->json();
            if (empty($data['data'])) {
                Log::info('FlightSyncService: no results', ['flight_id' => $teamFlight->id, 'number' => $teamFlight->flight_number]);
                return null;
            }

            $targetDate = $teamFlight->scheduled_at?->toDateString();
            $matched    = $targetDate
                ? collect($data['data'])->first(fn ($f) => ($f['flight_date'] ?? null) === $targetDate)
                : null;

            if (!$matched) {
                $fallback                   = $data['data'][0];
                $fallback['_date_mismatch'] = true;
                $fallback['_planned_date']  = $targetDate;
                return $fallback; // don't write to DB on mismatch
            }

            // Determine which leg to read based on flight direction
            $leg         = $teamFlight->direction === 'arrival' ? 'arrival' : 'departure';
            $actualTime  = $matched[$leg]['actual'] ?? $matched[$leg]['actual_runway'] ?? null;
            $estTime     = $matched[$leg]['estimated'] ?? null;

            $teamFlight->update([
                'flight_status'    => $matched['flight_status'] ?? null,
                'actual_at'        => $actualTime ? \Carbon\Carbon::parse($actualTime) : null,
                'estimated_at'     => $estTime    ? \Carbon\Carbon::parse($estTime)    : null,
                'delay_minutes'    => $matched[$leg]['delay'] ?? null,
                'flight_synced_at' => now(),
            ]);

            Log::info('FlightSyncService: synced flight record', ['flight_id' => $teamFlight->id, 'status' => $matched['flight_status'] ?? 'unknown']);

            return $matched;
        } catch (\Throwable $e) {
            Log::error('FlightSyncService: exception on flight record', ['flight_id' => $teamFlight->id, 'message' => $e->getMessage()]);
            return null;
        }
    }
}
