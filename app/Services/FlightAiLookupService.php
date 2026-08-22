<?php

namespace App\Services;

use App\Ai\Agents\FlightScheduleLookupAgent;
use App\Models\TeamFlight;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Falls back to an AI web search when AviationStack's live-tracking data
 * doesn't cover a flight's date (e.g. an event months in the future).
 * Read-only - never writes to the database, since a web-search-backed LLM
 * answer is a suggestion for a human to review, not a verified data feed.
 */
class FlightAiLookupService
{
    /**
     * @return array{found: bool, airline?: ?string, origin_iata?: ?string, destination_iata?: ?string,
     *     scheduled_departure?: ?string, scheduled_arrival?: ?string, terminal?: ?string, gate?: ?string,
     *     source_url?: ?string, confidence?: ?string, notes?: ?string}|null null means the lookup itself
     *     failed (network/API error) - distinct from a successful lookup that simply found nothing.
     */
    public function lookup(TeamFlight $teamFlight): ?array
    {
        $date = $teamFlight->scheduled_at?->toDateString();
        if (!$teamFlight->flight_number || !$date) {
            return null;
        }

        $prompt = sprintf(
            "Find the published scheduled departure and arrival time for flight %s on %s. Search the web for the airline's published schedule or a flight-tracking site.",
            $teamFlight->flight_number,
            $date,
        );

        try {
            $response = app(FlightScheduleLookupAgent::class)->prompt($prompt);

            return $response->structured;
        } catch (Throwable $e) {
            Log::warning('FlightAiLookupService: lookup failed', [
                'flight_id' => $teamFlight->id,
                'flight_number' => $teamFlight->flight_number,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
