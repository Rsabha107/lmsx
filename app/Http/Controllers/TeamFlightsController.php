<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Team;
use App\Models\TeamFlight;
use App\Services\FlightAiLookupService;
use App\Services\FlightMovementSync;
use App\Services\FlightRefresh;
use App\Services\FlightSyncService;
use App\Services\OagScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function update(Request $request, FlightMovementSync $movementSync, int $eventId, int $flightId): RedirectResponse
    {
        $flight    = TeamFlight::with('team:id,code')->where('event_id', $eventId)->findOrFail($flightId);
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

        $before = FlightMovementSync::before($flight);
        $original = $flight->getOriginal();

        // The flight and the plans that follow it change together or not at all.
        $change = DB::transaction(function () use ($flight, $validated, $before, $movementSync) {
            $flight->fill($validated);
            $flight->fill(FlightMovementSync::staleTracking($flight, $before));
            $flight->save();

            return FlightMovementSync::changed($flight, $before)
                ? $movementSync->sync($flight, $before, $flight->team?->code ?? '')
                : null;
        });

        $this->audit($flight, $original, $change);

        return redirect()->back()
            ->with('success', $this->summary($change))
            ->with('flight_change', $change && $change['movements'] ? $change : null);
    }

    /** "Flight updated." plus what happened to the plans built on it. */
    private function summary(?array $change): string
    {
        $movements = collect($change['movements'] ?? []);
        $updated = $movements->where('status', 'updated')->count();
        $review = $movements->where('status', 'needs_review')->count();
        $retimed = $movements->where('status', 'job_retimed')->count();

        return trim(implode(' ', array_filter([
            'Flight updated.',
            $updated ? "{$updated} planned ".($updated === 1 ? 'movement' : 'movements').' synced.' : null,
            $retimed ? "{$retimed} ".($retimed === 1 ? 'job' : 'jobs').' re-timed and supervisors notified.' : null,
            $review ? "{$review} ".($review === 1 ? 'movement already has a job' : 'movements already have jobs').' and must be updated by hand.' : null,
        ])));
    }

    private function audit(TeamFlight $flight, array $original, ?array $change): void
    {
        $diff = AuditLog::changes($flight, $original);
        if ($diff['to'] === []) {
            return;
        }

        $movements = collect($change['movements'] ?? []);
        AuditLog::change(
            'Flight updated',
            trim(($flight->team?->code ?? '').' '.$flight->direction.' '.($flight->flight_number ?? '')),
            $diff + [
                'movements_synced' => $movements->where('status', 'updated')->pluck('code')->values()->all(),
                'jobs_retimed' => $movements->where('status', 'job_retimed')->pluck('code')->values()->all(),
                'movements_to_review' => $movements->where('status', 'needs_review')->pluck('code')->values()->all(),
            ],
            $flight,
            $flight->event_id,
        );
    }

    public function destroy(int $eventId, int $flightId): RedirectResponse
    {
        FlightMovementSync::remove(TeamFlight::where('event_id', $eventId)->findOrFail($flightId));

        return redirect()->back()->with('success', 'Flight removed.');
    }

    /** Several flights at once from Event Teams (OAG, then AviationStack; no AI search): one result per flight. */
    public function syncBulk(Request $request, FlightRefresh $refresh, int $eventId): JsonResponse
    {
        $ids = $request->validate([
            'flight_ids' => 'required|array|min:1|max:60',
            'flight_ids.*' => 'integer',
        ])['flight_ids'];

        $flights = TeamFlight::where('event_id', $eventId)->whereKey($ids)
            ->with('team:id,code,team_name')->orderBy('scheduled_at')->get();

        $results = $flights->map(function (TeamFlight $flight) use ($refresh) {
            $result = $refresh->refresh($flight);
            $flight->refresh();

            return [
                'id' => $flight->id,
                'team' => $flight->team?->code,
                'flight_number' => $flight->flight_number,
                'direction' => $flight->direction,
                'ok' => $result['ok'],
                'source' => $result['source'],
                'error' => $result['error'],
                'moved_movements' => count($result['change']['movements'] ?? []),
                // What the new time did to plans and jobs (FlightMovementSync::sync), for the result list.
                'change' => $result['change'] ? $result['change'] + ['code' => $flight->team?->code] : null,
                'flight' => $flight->only(['id', 'scheduled_at', 'estimated_at', 'actual_at', 'delay_minutes', 'flight_status', 'flight_synced_at', 'flight_sync_attempted_at', 'flight_sync_error']),
            ];
        })->values();

        $ok = $results->where('ok', true)->count();
        $movements = $results->pluck('change')->filter()->flatMap(fn ($c) => $c['movements']);
        $summary = [
            'flights_changed' => $results->filter(fn ($r) => $r['change'] !== null)->count(),
            'movements_updated' => $movements->where('status', 'updated')->count(),
            'jobs_retimed' => $movements->where('status', 'job_retimed')->count(),
            'jobs_review' => $movements->where('status', 'needs_review')->count(),
            'clashes' => $movements->sum(fn ($m) => $m['clashes'] ?? 0),
        ];
        // Only what happened: failures, new times and what they moved; nothing when a figure is 0.
        AuditLog::change('Flights refreshed', "{$ok} of {$results->count()}", array_filter([
            'flights' => $results->pluck('flight_number')->all(),
            'failed' => $results->where('ok', false)->pluck('flight_number')->values()->all(),
            'new_times' => $results->filter(fn ($r) => $r['change'] !== null)->pluck('flight_number')->values()->all(),
            'movements_retimed' => $summary['movements_updated'],
            'jobs_retimed' => $summary['jobs_retimed'],
            'jobs_to_review' => $summary['jobs_review'],
        ]), null, $eventId);

        return response()->json(['refreshed' => $ok, 'failed' => $results->count() - $ok, 'results' => $results, 'summary' => $summary]);
    }

    /** Remember this refresh attempt and why it failed (null when it worked). */
    private function recordAttempt(TeamFlight $flight, ?string $error): void
    {
        $flight->forceFill(['flight_sync_attempted_at' => now(), 'flight_sync_error' => $error])->saveQuietly();
    }

    public function sync(FlightSyncService $service, OagScheduleService $oagService, FlightAiLookupService $aiLookup, int $eventId, int $flightId): JsonResponse
    {
        $teamFlight = TeamFlight::where('event_id', $eventId)
                                ->with(['originAirport', 'destinationAirport'])
                                ->findOrFail($flightId);

        // OAG first: schedule and status for any date, saved like any sync. Then AviationStack
        // (recent flights only, saved on an exact date match). Then an AI web search, never saved.
        $oag = $oagService->sync($teamFlight);

        if ($oag) {
            $this->recordAttempt($teamFlight, null);
            $oagDep = $oag['instance']['departure'] ?? [];
            $oagArr = $oag['instance']['arrival'] ?? [];
            $change = $oag['change'];

            return response()->json([
                'success'       => true,
                'source'        => 'oag',
                'saved'         => $oag['saved'],
                'flight_change' => $change && $change['movements'] ? $change : null,
                'flight_number' => $teamFlight->flight_number,
                'flight_status' => $oag['status']['flight_status'],
                'departure'     => [
                    'iata'      => $oagDep['airport']['iata'] ?? null,
                    'scheduled' => isset($oagDep['date']['local'], $oagDep['time']['local'])
                        ? "{$oagDep['date']['local']} {$oagDep['time']['local']}" : null,
                    'terminal'  => ($oagDep['terminal'] ?? '') ?: null,
                ] + $oag['status']['departure'],
                'arrival'       => [
                    'iata'      => $oagArr['airport']['iata'] ?? null,
                    'scheduled' => isset($oagArr['date']['local'], $oagArr['time']['local'])
                        ? "{$oagArr['date']['local']} {$oagArr['time']['local']}" : null,
                    'terminal'  => ($oagArr['terminal'] ?? '') ?: null,
                ] + $oag['status']['arrival'],
                'synced_at'     => now()->toISOString(),
            ]);
        }

        $raw = $service->syncFlightRecord($teamFlight);
        $this->recordAttempt($teamFlight, match (true) {
            !$raw => 'Not found by OAG or AviationStack; check the flight number and date.',
            !empty($raw['_date_mismatch']) => 'Found only on another date; check the flight number and date.',
            default => null,
        });

        if (!$raw || !empty($raw['_date_mismatch'])) {
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

        if (!$raw) {
            return response()->json([
                'success' => false,
                'message' => 'No flight data available for ' . $teamFlight->flight_number,
            ], 404);
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
