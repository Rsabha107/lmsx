<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamFlight;
use App\Services\FlightAiLookupService;
use App\Services\FlightSyncService;
use App\Services\OagScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TeamFlightsController extends Controller
{
    public function store(Request $request, int $eventId, string $teamCode): RedirectResponse
    {
        $validated = $request->validate([
            'direction'              => 'required|in:arrival,departure',
            'flight_number'          => 'nullable|string|max:20',
            'origin_airport_id'      => 'nullable|integer|exists:airports,id',
            'destination_airport_id' => 'nullable|integer|exists:airports,id',
            'terminal'               => 'nullable|string|max:50',
            'gate'                   => 'nullable|string|max:50',
            'scheduled_at'           => 'nullable|date',
            'party_size_total'       => 'nullable|integer|min:0',
            'party_size_players'     => 'nullable|integer|min:0',
            'party_size_staff'       => 'nullable|integer|min:0',
            'planned_bags'           => 'nullable|integer|min:0',
            'notes'                  => 'nullable|string',
        ]);

        // Convert team code to team_id
        $team = Team::where('event_id', $eventId)->where('code', $teamCode)->firstOrFail();

        TeamFlight::create([...$validated, 'event_id' => $eventId, 'team_id' => $team->id]);

        return redirect()->back()->with('success', 'Flight added.');
    }

    public function update(Request $request, int $eventId, int $flightId): RedirectResponse
    {
        $flight    = TeamFlight::where('event_id', $eventId)->findOrFail($flightId);
        $validated = $request->validate([
            'direction'              => 'required|in:arrival,departure',
            'flight_number'          => 'nullable|string|max:20',
            'origin_airport_id'      => 'nullable|integer|exists:airports,id',
            'destination_airport_id' => 'nullable|integer|exists:airports,id',
            'terminal'               => 'nullable|string|max:50',
            'gate'                   => 'nullable|string|max:50',
            'scheduled_at'           => 'nullable|date',
            'party_size_total'       => 'nullable|integer|min:0',
            'party_size_players'     => 'nullable|integer|min:0',
            'party_size_staff'       => 'nullable|integer|min:0',
            'planned_bags'           => 'nullable|integer|min:0',
            'notes'                  => 'nullable|string',
        ]);

        $flight->update($validated);

        return redirect()->back()->with('success', 'Flight updated.');
    }

    public function destroy(int $eventId, int $flightId): RedirectResponse
    {
        TeamFlight::where('event_id', $eventId)->findOrFail($flightId)->delete();

        return redirect()->back()->with('success', 'Flight removed.');
    }

    public function sync(FlightSyncService $service, OagScheduleService $oagService, FlightAiLookupService $aiLookup, int $eventId, int $flightId): JsonResponse
    {
        $teamFlight = TeamFlight::where('event_id', $eventId)
                                ->with(['originAirport', 'destinationAirport'])
                                ->findOrFail($flightId);

        $raw = $service->syncFlightRecord($teamFlight);

        if (!$raw) {
            return response()->json([
                'success' => false,
                'message' => 'No flight data available for ' . $teamFlight->flight_number,
            ], 404);
        }

        // AviationStack only has live/near-term data. When it can't find this
        // flight's actual date (e.g. it's months away), fall back to OAG's
        // published-schedule database - a real commercial data feed, so a
        // match is trusted and saved the same way a normal sync is. If OAG
        // also has nothing (not subscribed, or genuinely no data), fall back
        // further to an AI web search - read-only, never auto-saved.
        if (!empty($raw['_date_mismatch'])) {
            $oag = $oagService->lookupSchedule($teamFlight);

            if ($oag) {
                $oagDep = $oag['departure'] ?? [];
                $oagArr = $oag['arrival'] ?? [];
                $leg = $teamFlight->direction === 'arrival' ? $oagArr : $oagDep;

                $scheduledAt = null;
                if (!empty($leg['date']['local']) && !empty($leg['time']['local'])) {
                    $scheduledAt = \Carbon\Carbon::parse("{$leg['date']['local']} {$leg['time']['local']}");
                    $teamFlight->update(['scheduled_at' => $scheduledAt, 'flight_synced_at' => now()]);
                }

                return response()->json([
                    'success'       => true,
                    'source'        => 'oag',
                    'saved'         => (bool) $scheduledAt,
                    'flight_number' => $teamFlight->flight_number,
                    'departure'     => [
                        'iata'      => $oagDep['airport']['iata'] ?? null,
                        'scheduled' => isset($oagDep['date']['local'], $oagDep['time']['local'])
                            ? "{$oagDep['date']['local']} {$oagDep['time']['local']}" : null,
                        'terminal'  => $oagDep['terminal'] ?: null,
                    ],
                    'arrival'       => [
                        'iata'      => $oagArr['airport']['iata'] ?? null,
                        'scheduled' => isset($oagArr['date']['local'], $oagArr['time']['local'])
                            ? "{$oagArr['date']['local']} {$oagArr['time']['local']}" : null,
                        'terminal'  => $oagArr['terminal'] ?: null,
                    ],
                    'synced_at'     => now()->toISOString(),
                ]);
            }

            $ai = $aiLookup->lookup($teamFlight);

            // Show whatever the AI turned up, even when it couldn't confirm an
            // exact schedule (found=false) - its notes (e.g. "this flight may
            // not operate on Sundays") are often still useful, and are more
            // honest than silently falling back to a generic "no data" message.
            if ($ai) {
                return response()->json([
                    'success'        => true,
                    'source'         => 'ai',
                    'saved'          => false,
                    'ai_found'       => !empty($ai['found']),
                    'flight_number'  => $teamFlight->flight_number,
                    'airline'        => $ai['airline'] ?? null,
                    'departure'      => [
                        'iata'      => $ai['origin_iata'] ?? null,
                        'scheduled' => $ai['scheduled_departure'] ?? null,
                        'terminal'  => $ai['terminal'] ?? null,
                    ],
                    'arrival'        => [
                        'iata'      => $ai['destination_iata'] ?? null,
                        'scheduled' => $ai['scheduled_arrival'] ?? null,
                        'gate'      => $ai['gate'] ?? null,
                    ],
                    'ai_confidence'  => $ai['confidence'] ?? null,
                    'ai_notes'       => $ai['notes'] ?? null,
                    'ai_source_url'  => $ai['source_url'] ?? null,
                    'synced_at'      => now()->toISOString(),
                ]);
            }
        }

        $dep = $raw['departure'] ?? [];
        $arr = $raw['arrival']   ?? [];

        $depActual = $dep['actual'] ?? $dep['actual_runway'] ?? null;
        if (!$depActual && !empty($dep['delay']) && !empty($dep['scheduled'])) {
            $depActual = \Carbon\Carbon::parse($dep['scheduled'])->addMinutes((int) $dep['delay'])->toIso8601String();
        }
        $arrActual    = $arr['actual']    ?? $arr['actual_runway']    ?? null;
        $arrEstimated = $arr['estimated'] ?? $arr['estimated_runway'] ?? null;
        if (!$arrEstimated && !$arrActual && !empty($arr['delay']) && !empty($arr['scheduled'])) {
            $arrEstimated = \Carbon\Carbon::parse($arr['scheduled'])->addMinutes((int) $arr['delay'])->toIso8601String();
        }

        Log::info("Flight sync for TeamFlight ID {$teamFlight->id} ({$teamFlight->flight_number}): " . ($raw ? 'Data found' : 'No data') . ", dep actual: {$depActual}, arr actual: {$arrActual}, arr estimated: {$arrEstimated}"); 
        Log::debug('Full flight data', ['raw' => $raw]);
        
        return response()->json([
            'success'       => true,
            'source'        => 'aviationstack',
            'saved'         => empty($raw['_date_mismatch']),
            'date_mismatch' => !empty($raw['_date_mismatch']),
            'planned_date'  => $raw['_planned_date'] ?? null,
            'flight_date'   => $raw['flight_date']   ?? null,
            'flight_number' => $teamFlight->flight_number,
            'flight_status' => $raw['flight_status'] ?? null,
            'airline'       => $raw['airline']['name'] ?? null,
            'departure'     => [
                'iata'      => $dep['iata']      ?? null,
                'airport'   => $dep['airport']   ?? null,
                'scheduled' => $dep['scheduled'] ?? null,
                'actual'    => $depActual,
                'estimated' => $dep['estimated'] ?? null,
                'terminal'  => $dep['terminal']  ?? null,
                'gate'      => $dep['gate']      ?? $teamFlight->gate,
                'delay'     => $dep['delay']     ?? null,
            ],
            'arrival'       => [
                'iata'      => $arr['iata']      ?? null,
                'airport'   => $arr['airport']   ?? null,
                'scheduled' => $arr['scheduled'] ?? null,
                'actual'    => $arrActual,
                'estimated' => $arrEstimated,
                'terminal'  => $arr['terminal']  ?? null,
                'gate'      => $arr['gate']      ?? null,
                'delay'     => $arr['delay']     ?? null,
            ],
            'synced_at'     => now()->toISOString(),
        ]);
    }
}
