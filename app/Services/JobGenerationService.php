<?php

namespace App\Services;

use App\Models\Checkpoint;
use App\Models\CheckpointTemplate;
use App\Models\Driver;
use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\MovementTemplate;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for generating logistics jobs from planned movements.
 * 
 * This service handles:
 * - Generating jobs from movements
 * - Snapshotting checkpoint sequences from templates
 * - Assigning vehicles and drivers
 */
class JobGenerationService
{
    protected SettingsService $settingsService;

    public function __construct(SettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }
    /**
     * Generate a job from a movement.
     * 
     * This snapshots the checkpoint template at the time of generation,
     * ensuring historical integrity.
     */
    public function generateJobFromMovement(Movement $movement, ?array $options = []): JobOperation
    {
        if ($movement->isBusMovement()) {
            throw new \RuntimeException('Cannot generate a job for a BUS movement — it has no reference time to schedule against.');
        }

        return DB::transaction(function () use ($movement, $options) {
            // Create the job
            $job = JobOperation::create([
                'job_id' => $this->generateJobId(),
                'event_id' => $movement->event_id,
                'movement_id' => $movement->id,
                'plan_id' => $movement->plan_id,
                'team_id' => $movement->team_id,
                'functional_area' => $movement->functional_area,
                'supervisor_id' => $movement->field_supervisor_id ?? $options['supervisor_id'] ?? null,
                'driver_id' => $movement->driver_id,
                'vehicle_id' => $movement->vehicle_id,
                'status' => 'pending',
            ]);

            // Snapshot checkpoints from the checkpoint template
            if ($movement->checkpointTemplate) {
                $this->snapshotCheckpoints($job, $movement->checkpointTemplate);
            }

            // Update the movement with job info
            $movement->update([
                'job_id' => $job->job_id,
                'job_generated_at' => now(),
            ]);

            return $job;
        });
    }

    /**
     * Generate multiple jobs from movements.
     */
    public function generateJobsFromMovements(array $movementIds, ?array $options = []): array
    {
        $jobs = [];

        // BUS movements (flight_number === 'BUS') are silently skipped
        // rather than blocking the whole batch — they have no reference
        // time to generate a job against.
        $movements = Movement::with(['checkpointTemplate.checkpoints', 'flight'])
            ->whereIn('id', $movementIds)
            ->whereNull('job_id')
            ->get()
            ->reject(fn (Movement $movement) => $movement->isBusMovement());

        foreach ($movements as $movement) {
            $jobs[] = $this->generateJobFromMovement($movement, $options);
        }

        return $jobs;
    }

    /**
     * Snapshot checkpoints from template to job.
     * 
     * This creates a historical snapshot so that changes to the template
     * don't affect existing jobs.
     */
    protected function snapshotCheckpoints(JobOperation $job, CheckpointTemplate $template): void
    {
        $checkpoints = $template->checkpoints()
            ->orderBy('pivot_order')
            ->get();

        $totalCheckpoints = $checkpoints->count();
        $movement = $job->movement;
        $referenceTime = $movement ? $this->resolveReferenceTime($movement) : null;

        foreach ($checkpoints as $checkpoint) {
            // Only checkpoints with an explicit configured offset get a
            // scheduled_at; there is no cumulative or hardcoded fallback.
            $scheduledAt = $movement ? $this->computeCheckpointTimeFromReference($movement, $checkpoint, $referenceTime) : null;

            JobCheckpoint::create([
                'job_id' => $job->id,
                'event_id' => $job->event_id,
                'checkpoint_id' => $checkpoint->id,
                'order' => $checkpoint->pivot->order,
                'name' => $checkpoint->name,
                'type' => $checkpoint->type,
                'description' => $checkpoint->description,
                'requires_photo' => $checkpoint->requires_photo,
                'requires_signature' => $checkpoint->requires_signature,
                'is_required' => $checkpoint->pivot->is_required ?? true,
                'estimated_minutes' => $checkpoint->pivot->estimated_minutes,
                'scheduled_at' => $scheduledAt,
                'planned_bags' => $checkpoint->requires_baggage_count ? $movement?->flight?->planned_bags : null,
                'state' => 'pending',
            ]);
        }

        // Update job totals
        $job->update([
            'checkpoints_total' => $totalCheckpoints,
            'checkpoints_completed' => 0,
        ]);
    }

    /**
     * Generate a unique job ID.
     */
    protected function generateJobId(): string
    {
        $date = now()->format('Ymd');
        $count = JobOperation::whereDate('created_at', now())->count() + 1;
        
        return sprintf('JOB-%s-%04d', $date, $count);
    }

    /**
     * Recompute a single movement's window from the current effective
     * settings offset, preserving its existing duration. No-ops (returns
     * false) for movements that already have a job, kinds that aren't
     * offset-driven (training/daily_ops), or movements with no resolvable
     * reference time (flight/match).
     */
    public function recomputeMovementWindow(Movement $movement): bool
    {
        if ($movement->hasJob()) {
            return false;
        }

        if (!in_array($movement->kind, ['arrival', 'departure', 'transfer', 'match', 'training'], true)) {
            return false;
        }

        if ($movement->isBusMovement()) {
            return false;
        }

        $referenceTime = $this->resolveReferenceTime($movement);

        if (!$referenceTime) {
            return false;
        }

        $firstCheckpoint = $movement->checkpointTemplate?->checkpoints->first();
        $offsetMinutes = $this->resolveOffsetMinutes($firstCheckpoint, $movement->kind, $movement->event_id);

        $durationMinutes = ($movement->window_start && $movement->window_end)
            ? $movement->window_start->diffInMinutes($movement->window_end)
            : 30;

        $newWindowStart = $referenceTime->copy()->addMinutes($offsetMinutes);
        $newWindowEnd = $newWindowStart->copy()->addMinutes($durationMinutes);

        $unchanged = $movement->window_start?->equalTo($newWindowStart) && $movement->window_end?->equalTo($newWindowEnd);
        if ($unchanged) {
            return false;
        }

        $movement->update([
            'window_start' => $newWindowStart,
            'window_end' => $newWindowEnd,
        ]);

        return true;
    }

    /**
     * Recompute windows for every eligible movement of a given kind in an
     * event (no job generated yet). Returns the count of movements whose
     * window actually changed.
     */
    public function recomputeWindowsForEventAndKind(int $eventId, string $movementType): int
    {
        if ($movementType === 'daily_ops') {
            return 0;
        }

        $movements = Movement::where('event_id', $eventId)
            ->where('kind', $movementType)
            ->withoutJob()
            ->with(['flight', 'match', 'plan', 'checkpointTemplate.checkpoints'])
            ->get();

        $updatedCount = 0;

        foreach ($movements as $movement) {
            if ($this->recomputeMovementWindow($movement)) {
                $updatedCount++;
            }
        }

        return $updatedCount;
    }

    /**
     * Resolve the movement-type's reference time: the flight for
     * arrival/departure/transfer, the match kick-off for match, or the
     * team's training start time for training. Returns null if there's
     * nothing to resolve against (e.g. no flight/match/stay recorded yet).
     */
    protected function resolveReferenceTime(Movement $movement): ?Carbon
    {
        return match ($movement->kind) {
            'arrival', 'departure' => $movement->flight?->scheduled_at,
            'transfer' => \App\Models\TeamFlight::where('event_id', $movement->event_id)
                ->where('team_id', $movement->team_id)
                ->where('direction', 'arrival')
                ->first()?->scheduled_at,
            'match' => $movement->match?->kick_off,
            'training' => \App\Models\TeamTraining::where('event_id', $movement->event_id)
                ->where('team_id', $movement->team_id)
                ->first()?->training_start_at,
            default => null,
        };
    }

    /**
     * Resolve the effective offset in minutes for a movement/leg. A
     * checkpoint-specific override (event-scoped, then global-scoped) on the
     * FIRST checkpoint of the checkpoint template drives the whole
     * movement's window when configured; otherwise falls back to the
     * event->global movement-offset cascade.
     */
    protected function resolveOffsetMinutes(?Checkpoint $firstCheckpoint, string $movementKind, ?int $eventId): int
    {
        if ($firstCheckpoint) {
            $checkpointOffset = $this->settingsService->getCheckpointOffset($firstCheckpoint->id, $movementKind, $eventId);
            if ($checkpointOffset !== null) {
                return $checkpointOffset;
            }
        }

        return $this->settingsService->getMovementOffset($movementKind, $eventId);
    }

    /**
     * When a template's match leg starts relative to kick-off, and which setting
     * that comes from - the same resolution generation uses, so the New Plan
     * preview shows the times the movements will actually get.
     *
     * @return array{minutes: int, source: string}|null null when the template has no match leg
     */
    public function matchStartOffset(\App\Models\MovementTemplate $template, ?int $eventId): ?array
    {
        $leg = $template->legs->firstWhere('leg_type', 'match');
        if (!$leg) {
            return null;
        }

        $checkpoint = $leg->checkpointTemplate?->checkpoints->first();
        $minutes = $this->resolveOffsetMinutes($checkpoint, 'match', $eventId);

        $checkpointScope = $checkpoint ? $this->settingsService->offsetScope('match', $eventId, $checkpoint->id) : null;
        $source = $checkpointScope
            ? sprintf('the %s setting for the "%s" checkpoint', $checkpointScope === 'event' ? 'event' : 'global', $checkpoint->name)
            : match ($this->settingsService->offsetScope('match', $eventId)) {
                'event' => 'the event\'s match default',
                'global' => 'the global match default',
                default => 'no offset configured yet',
            };

        return ['minutes' => $minutes, 'source' => $source];
    }

    /**
     * Compute a single checkpoint's scheduled time from an explicit
     * checkpoint-specific offset (event-scoped, then global-scoped) only.
     * Returns null if no offset is configured for this checkpoint/movement
     * type, or if the movement's reference time can't be resolved — there
     * is no cumulative or hardcoded fallback.
     */
    public function computeCheckpointTime(Movement $movement, Checkpoint $checkpoint): ?Carbon
    {
        return $this->computeCheckpointTimeFromReference($movement, $checkpoint, $this->resolveReferenceTime($movement));
    }

    /**
     * Same as computeCheckpointTime(), but takes an already-resolved
     * reference time instead of re-resolving it. A movement's reference
     * time is the same for every one of its checkpoints, but resolving it
     * for 'transfer'/'training' kinds runs its own query — callers that
     * loop over all of a movement's checkpoints (snapshotCheckpoints,
     * estimateCheckpointSchedule) resolve it once and pass it in here to
     * avoid re-querying per checkpoint.
     */
    protected function computeCheckpointTimeFromReference(Movement $movement, Checkpoint $checkpoint, ?Carbon $referenceTime): ?Carbon
    {
        if (!$referenceTime) {
            return null;
        }

        $checkpointOffset = $this->settingsService->getCheckpointOffset($checkpoint->id, $movement->kind, $movement->event_id);
        if ($checkpointOffset === null) {
            return null;
        }

        return $referenceTime->copy()->addMinutes($checkpointOffset);
    }

    /**
     * Live pre-job estimate of each checkpoint's scheduled time for a
     * movement that hasn't generated a job yet, using the same logic as
     * snapshotCheckpoints(). Returns [checkpoint_id => 'Y-m-d H:i:s'|null].
     */
    public function estimateCheckpointSchedule(Movement $movement): array
    {
        if ($movement->isBusMovement()) {
            return [];
        }

        $checkpoints = $movement->checkpointTemplate?->checkpoints;
        if (!$checkpoints || $checkpoints->isEmpty()) {
            return [];
        }

        $referenceTime = $this->resolveReferenceTime($movement);

        $result = [];
        foreach ($checkpoints as $checkpoint) {
            $result[$checkpoint->id] = $this->computeCheckpointTimeFromReference($movement, $checkpoint, $referenceTime)?->format('Y-m-d H:i:s');
        }

        return $result;
    }

    /**
     * Build the full per-checkpoint display payload for one movement — real
     * job-checkpoint state/times when a job exists, otherwise the template
     * checkpoint with a live schedule estimate. This is intentionally
     * per-movement (not batch): it's used by the on-demand checkpoints
     * endpoint that the Plans page calls when a single movement is
     * selected, rather than being eagerly computed for every movement on
     * the list (which is what made that page slow at a few hundred rows —
     * see PlanManagementController@checkpoints).
     *
     * Expects $movement to have `checkpointTemplate.checkpoints`,
     * `job.checkpoints.completedBy`, and `flight` already loaded.
     */
    public function buildCheckpointsPayload(Movement $movement): array
    {
        if ($movement->isBusMovement()) {
            return [];
        }

        $checkpoints = $movement->checkpointTemplate?->checkpoints;
        if (!$checkpoints || $checkpoints->isEmpty()) {
            return [];
        }

        $jobCheckpoints = $movement->job?->checkpoints ?? collect();
        $estimatedSchedule = $movement->job ? [] : $this->estimateCheckpointSchedule($movement);

        return $checkpoints->map(function ($checkpoint) use ($jobCheckpoints, $estimatedSchedule, $movement) {
            $jobCheckpoint = $jobCheckpoints->firstWhere('checkpoint_id', $checkpoint->id);

            if ($jobCheckpoint) {
                return [
                    'id' => $checkpoint->id,
                    'name' => $checkpoint->name,
                    'type' => $checkpoint->type,
                    'requires_photo' => $checkpoint->requires_photo,
                    'requires_signature' => $checkpoint->requires_signature,
                    'requires_baggage_count' => $checkpoint->requires_baggage_count,
                    'planned_bags' => $jobCheckpoint->planned_bags,
                    'bags_loaded' => $jobCheckpoint->bags_loaded,
                    'food_bags' => $jobCheckpoint->food_bags,
                    'oversized_pieces' => $jobCheckpoint->oversized_pieces,
                    'state' => $jobCheckpoint->state ?? 'pending',
                    'scheduled_at' => $jobCheckpoint->scheduled_at?->format('Y-m-d H:i:s'),
                    'started_at' => $jobCheckpoint->started_at?->format('Y-m-d H:i:s'),
                    'completed_at' => $jobCheckpoint->completed_at?->format('Y-m-d H:i:s'),
                    'completed_by' => $jobCheckpoint->completedBy?->name,
                    'photo_path' => $jobCheckpoint->photo_path,
                    'signature_path' => $jobCheckpoint->signature_path,
                ];
            }

            return [
                'id' => $checkpoint->id,
                'name' => $checkpoint->name,
                'type' => $checkpoint->type,
                'requires_photo' => $checkpoint->requires_photo,
                'requires_signature' => $checkpoint->requires_signature,
                'requires_baggage_count' => $checkpoint->requires_baggage_count,
                'planned_bags' => $checkpoint->requires_baggage_count ? $movement->flight?->planned_bags : null,
                'bags_loaded' => null,
                'food_bags' => null,
                'oversized_pieces' => null,
                'state' => 'pending',
                'scheduled_at' => null,
                'estimated_at' => $estimatedSchedule[$checkpoint->id] ?? null,
                'started_at' => null,
                'completed_at' => null,
                'completed_by' => null,
                'photo_path' => null,
                'signature_path' => null,
            ];
        })->values()->toArray();
    }

    /**
     * Create a plan from a movement template.
     */
    public function createPlanFromTemplate(
        MovementTemplate $template,
        array $planData,
        array $teamAssignments = []
    ): Plan {
        return DB::transaction(function () use ($template, $planData, $teamAssignments) {
            // Debug: Log the planData being used to create the Plan
            Log::info('JobGenerationService::createPlanFromTemplate - Creating plan', [
                'date_param' => $planData['date'],
                'base_time_param' => isset($planData['base_time']) ? $planData['base_time']->toDateTimeString() : 'not set',
                'event_id' => $planData['event_id'] ?? null,
            ]);
            
            // Create the plan
            $plan = Plan::create([
                'code' => $this->generatePlanCode($planData['date']),
                'name' => $planData['name'],
                'date' => $planData['date'],
                'status' => $planData['status'] ?? 'draft',
                'event_id' => $planData['event_id'] ?? null,
                'movement_template_id' => $template->id,
                'functional_area' => $template->functional_area,
                'notes' => $planData['notes'] ?? null,
                'created_by' => $planData['created_by'] ?? (Auth::check() ? Auth::id() : null),
            ]);
            
            // Debug: Log what was created
            Log::info('JobGenerationService::createPlanFromTemplate - Plan created', [
                'plan_id' => $plan->id,
                'date_attribute' => $plan->date ? $plan->date->toDateTimeString() : 'null',
                'raw_date_value' => $plan->getAttributes()['date'],
            ]);

            // Get flight passenger count if flight_id is provided
            $flightPassengerCount = null;
            if (!empty($planData['flight_id'])) {
                $flight = \App\Models\TeamFlight::find($planData['flight_id']);
                $flightPassengerCount = $flight?->party_size_total;
            }

            // Create movements from template legs
            $legs = $template->legs()->with('checkpointTemplate.checkpoints')->get();
            
            // Track cumulative time for chaining movements
            $currentTime = $planData['base_time'] ?? now();
            
            // Check if we're creating movements for multiple teams (bulk mode)
            $bulkTeamIds = $teamAssignments['teams'] ?? null;
            $teamStartTimes = $teamAssignments['team_start_times'] ?? null;
            $teamFlightIds = $teamAssignments['team_flight_ids'] ?? null;
            $teamMatchIds = $teamAssignments['team_match_ids'] ?? null;
            
            // If team-specific start times are provided, loop through teams first
            if ($bulkTeamIds && $teamStartTimes) {
                // Bulk mode with per-team start times
                foreach ($bulkTeamIds as $teamId) {
                    $teamStartTime = isset($teamStartTimes[$teamId]) 
                        ? Carbon::parse($planData['date'] . ' ' . $teamStartTimes[$teamId], config('app.timezone'))
                        : $currentTime->copy();
                    
                    $teamCurrentTime = $teamStartTime->copy();
                    
                    // Get passenger count for this team from their flight
                    $teamPassengerCount = null;
                    $movementFlightId = null;
                    $movementFlightDirection = null;
                    if (isset($teamFlightIds[$teamId]) && $teamFlightIds[$teamId]) {
                        $teamFlight = \App\Models\TeamFlight::find($teamFlightIds[$teamId]);
                        if ($teamFlight) {
                            $teamPassengerCount = $teamFlight->party_size_total;
                            $movementFlightId = $teamFlight->id;
                            $movementFlightDirection = $teamFlight->direction;
                        }
                    }
                    
                    // Create all legs for this team
                    foreach ($legs as $leg) {
                        $scheduledDeparture = null;
                        $scheduledArrival = null;
                        // A 'BUS' flight_number is a placeholder meaning the
                        // team travels by road, not an actual flight — no
                        // real reference time exists, so the window stays
                        // null and the sequential-timing fallback below must
                        // not kick in for this leg.
                        $isBusLeg = false;

                        // Smart timing for arrival legs
                        if ($leg->leg_type === 'arrival' && $plan->event_id) {
                            $arrivalFlight = \App\Models\TeamFlight::where('event_id', $plan->event_id)
                                ->where('team_id', $teamId)
                                ->where('direction', 'arrival')
                                ->first();

                            if ($arrivalFlight && $arrivalFlight->flight_number === 'BUS') {
                                $isBusLeg = true;
                            } elseif ($arrivalFlight && $arrivalFlight->scheduled_at) {
                                $offsetMinutes = $this->resolveOffsetMinutes($leg->checkpointTemplate?->checkpoints->first(), 'arrival', $plan->event_id);
                                $scheduledDeparture = $arrivalFlight->scheduled_at->copy()->addMinutes($offsetMinutes);
                                $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                            }
                        } elseif ($leg->leg_type === 'departure' && $plan->event_id) {
                            $departureFlight = \App\Models\TeamFlight::where('event_id', $plan->event_id)
                                ->where('team_id', $teamId)
                                ->where('direction', 'departure')
                                ->first();

                            if ($departureFlight && $departureFlight->flight_number === 'BUS') {
                                $isBusLeg = true;
                            } elseif ($departureFlight && $departureFlight->scheduled_at) {
                                $offsetMinutes = $this->resolveOffsetMinutes($leg->checkpointTemplate?->checkpoints->first(), 'departure', $plan->event_id);
                                $scheduledDeparture = $departureFlight->scheduled_at->copy()->addMinutes($offsetMinutes);
                                $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                            }
                        } elseif ($leg->leg_type === 'match' && $plan->event_id) {
                            // If we have a specific match_id for this team, use it
                            $match = null;
                            if (isset($teamMatchIds[$teamId]) && $teamMatchIds[$teamId]) {
                                $match = \App\Models\GameMatch::find($teamMatchIds[$teamId]);
                            } else {
                                // Fallback: find the team's first match if no specific match_id provided
                                $match = \App\Models\GameMatch::where('event_id', $plan->event_id)
                                    ->where(function($q) use ($teamId) {
                                        $q->where('team1_id', $teamId)
                                          ->orWhere('team2_id', $teamId);
                                    })
                                    ->orderBy('kick_off')
                                    ->first();
                            }

                            if ($match && $match->kick_off) {
                                $offsetMinutes = $this->resolveOffsetMinutes($leg->checkpointTemplate?->checkpoints->first(), 'match', $plan->event_id);
                                $scheduledDeparture = $match->kick_off->copy()->addMinutes($offsetMinutes);
                                $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                            }
                        } elseif ($leg->leg_type === 'transfer' && $plan->event_id) {
                            $arrivalFlight = \App\Models\TeamFlight::where('event_id', $plan->event_id)
                                ->where('team_id', $teamId)
                                ->where('direction', 'arrival')
                                ->first();
                            
                            if ($arrivalFlight && $arrivalFlight->scheduled_at) {
                                $offsetMinutes = $this->resolveOffsetMinutes($leg->checkpointTemplate?->checkpoints->first(), 'transfer', $plan->event_id);
                                $scheduledDeparture = $arrivalFlight->scheduled_at->copy()->addMinutes($offsetMinutes);
                                $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                            }
                        }
                        
                        // For match legs, skip this team if they don't have a match
                        if ($leg->leg_type === 'match' && (!$scheduledDeparture || !$scheduledArrival)) {
                            continue;
                        }

                        // Fallback to team start time if smart timing not available
                        if (!$isBusLeg && (!$scheduledDeparture || !$scheduledArrival)) {
                            $scheduledDeparture = $teamCurrentTime->copy();
                            $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                        }

                        // Update current time for next leg (BUS legs have no
                        // window, so they don't shift subsequent legs' timing)
                        if ($scheduledArrival) {
                            $teamCurrentTime = $scheduledArrival->copy();
                        }
                        
                        // Determine passenger count: prioritize team's flight > team model > template estimate
                        $passengerCount = 0;
                        if ($teamPassengerCount !== null) {
                            $passengerCount = $teamPassengerCount;
                        } elseif ($teamId) {
                            $team = Team::find($teamId);
                            $passengerCount = $team?->party_size_total ?? 0;
                        }
                        if ($passengerCount === 0) {
                            $passengerCount = $leg->estimated_passengers ?? 0;
                        }
                        
                        // Auto-assign vehicle and driver
                        $vehicleId = $passengerCount > 0 
                            ? $this->findAvailableVehicle($passengerCount, $scheduledDeparture, $scheduledArrival)
                            : null;
                        
                        $driverId = $vehicleId 
                            ? $this->findAvailableDriver($scheduledDeparture, $scheduledArrival)
                            : null;
                        
                        // Auto-fetch flight_id from event team flights based on movement kind.
                        // The bulk-assigned flight only applies to the leg matching its own
                        // direction — a departure leg must never inherit an arrival flight.
                        $legMovementFlightId = ($movementFlightId && $movementFlightDirection === $leg->leg_type) ? $movementFlightId : null;
                        if (!$legMovementFlightId && $plan->event_id && in_array($leg->leg_type, ['arrival', 'departure'])) {
                            $flight = \App\Models\TeamFlight::where('event_id', $plan->event_id)
                                ->where('team_id', $teamId)
                                ->where('direction', $leg->leg_type)
                                ->first();
                            if ($flight) {
                                $legMovementFlightId = $flight->id;
                            }
                        }
                        
                        // Auto-fetch accommodation_id from event team stay
                        $movementAccommodationId = null;
                        if (!empty($planData['accommodation_id'])) {
                            $movementAccommodationId = $planData['accommodation_id'];
                        } elseif ($plan->event_id && $teamId) {
                            $stay = \App\Models\TeamStay::where('event_id', $plan->event_id)
                                ->where('team_id', $teamId)
                                ->first();
                            if ($stay) {
                                $movementAccommodationId = $stay->id;
                            }
                        }
                        
                        Movement::create([
                            'code' => $this->generateMovementCode($plan),
                            'plan_id' => $plan->id,
                            'event_id' => $plan->event_id,
                            'team_id' => $teamId,
                            'flight_id' => $legMovementFlightId,
                            'accommodation_id' => $movementAccommodationId,
                            'checkpoint_template_id' => $leg->checkpoint_template_id,
                            'kind' => $leg->leg_type,
                            'functional_area' => $template->functional_area,
                            'from_location' => $leg->from_location,
                            'to_location' => $leg->to_location,
                            'window_start' => $scheduledDeparture,
                            'window_end' => $scheduledArrival,
                            'vehicle_id' => $vehicleId,
                            'driver_id' => $driverId,
                            'passengers' => $passengerCount,
                            'status' => 'scheduled',
                            'source' => 'template',
                        ]);
                    }
                }
            } elseif ($bulkTeamIds) {
                // Bulk mode without team start times: loop through teams first, calculate smart timing
                // This matches "Bulk by Matches" pattern but uses smart timing
                foreach ($bulkTeamIds as $teamId) {
                    $team = Team::find($teamId);
                    $teamCurrentTime = $currentTime->copy();
                    
                    // For match legs, check if this team has a match
                    $hasMatch = false;
                    if ($plan->event_id) {
                        $match = \App\Models\GameMatch::where('event_id', $plan->event_id)
                            ->where(function($q) use ($teamId) {
                                $q->where('team1_id', $teamId)
                                  ->orWhere('team2_id', $teamId);
                            })
                            ->exists();
                        $hasMatch = $match;
                    }
                    
                    // Create all legs for this team
                    foreach ($legs as $leg) {
                        $scheduledDeparture = null;
                        $scheduledArrival = null;
                        // A 'BUS' flight_number is a placeholder meaning the
                        // team travels by road, not an actual flight — no
                        // real reference time exists, so the window stays
                        // null and the sequential-timing fallback below must
                        // not kick in for this leg.
                        $isBusLeg = false;

                        // Smart timing based on leg type and team data
                        if ($leg->leg_type === 'arrival' && $plan->event_id) {
                            $arrivalFlight = \App\Models\TeamFlight::where('event_id', $plan->event_id)
                                ->where('team_id', $teamId)
                                ->where('direction', 'arrival')
                                ->first();

                            if ($arrivalFlight && $arrivalFlight->flight_number === 'BUS') {
                                $isBusLeg = true;
                            } elseif ($arrivalFlight && $arrivalFlight->scheduled_at) {
                                $offsetMinutes = $this->resolveOffsetMinutes($leg->checkpointTemplate?->checkpoints->first(), 'arrival', $plan->event_id);
                                $scheduledDeparture = $arrivalFlight->scheduled_at->copy()->addMinutes($offsetMinutes);
                                $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                            }
                        } elseif ($leg->leg_type === 'departure' && $plan->event_id) {
                            $departureFlight = \App\Models\TeamFlight::where('event_id', $plan->event_id)
                                ->where('team_id', $teamId)
                                ->where('direction', 'departure')
                                ->first();

                            if ($departureFlight && $departureFlight->flight_number === 'BUS') {
                                $isBusLeg = true;
                            } elseif ($departureFlight && $departureFlight->scheduled_at) {
                                $offsetMinutes = $this->resolveOffsetMinutes($leg->checkpointTemplate?->checkpoints->first(), 'departure', $plan->event_id);
                                $scheduledDeparture = $departureFlight->scheduled_at->copy()->addMinutes($offsetMinutes);
                                $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                            }
                        } elseif ($leg->leg_type === 'match' && $plan->event_id) {
                            // If we have a specific match_id for this team, use it
                            $match = null;
                            if (isset($teamMatchIds[$teamId]) && $teamMatchIds[$teamId]) {
                                $match = \App\Models\GameMatch::find($teamMatchIds[$teamId]);
                            } else {
                                // Fallback: find the team's first match if no specific match_id provided
                                $match = \App\Models\GameMatch::where('event_id', $plan->event_id)
                                    ->where(function($q) use ($teamId) {
                                        $q->where('team1_id', $teamId)
                                          ->orWhere('team2_id', $teamId);
                                    })
                                    ->orderBy('kick_off')
                                    ->first();
                            }

                            if ($match && $match->kick_off) {
                                $offsetMinutes = $this->resolveOffsetMinutes($leg->checkpointTemplate?->checkpoints->first(), 'match', $plan->event_id);
                                $scheduledDeparture = $match->kick_off->copy()->addMinutes($offsetMinutes);
                                $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                            }
                        } elseif ($leg->leg_type === 'transfer' && $plan->event_id) {
                            $arrivalFlight = \App\Models\TeamFlight::where('event_id', $plan->event_id)
                                ->where('team_id', $teamId)
                                ->where('direction', 'arrival')
                                ->first();

                            if ($arrivalFlight && $arrivalFlight->scheduled_at) {
                                $offsetMinutes = $this->resolveOffsetMinutes($leg->checkpointTemplate?->checkpoints->first(), 'transfer', $plan->event_id);
                                $scheduledDeparture = $arrivalFlight->scheduled_at->copy()->addMinutes($offsetMinutes);
                                $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                            }
                        }

                        // For match legs, skip this team if they don't have a match
                        if ($leg->leg_type === 'match' && (!$scheduledDeparture || !$scheduledArrival)) {
                            // Skip this leg for this team - continue to next leg
                            continue;
                        }

                        // Fallback to sequential timing if smart timing not available
                        if (!$isBusLeg && (!$scheduledDeparture || !$scheduledArrival)) {
                            $scheduledDeparture = $teamCurrentTime->copy();
                            $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                        }

                        // Update team's current time for next leg (BUS legs
                        // have no window, so they don't shift subsequent legs)
                        if ($scheduledArrival) {
                            $teamCurrentTime = $scheduledArrival->copy();
                        }
                        
                        // Determine passenger count: prioritize team > template estimate
                        $passengerCount = $team?->party_size_total ?? 0;
                        if ($passengerCount === 0) {
                            $passengerCount = $leg->estimated_passengers ?? 0;
                        }
                        
                        // Auto-assign vehicle based on passenger count and availability
                        $vehicleId = $passengerCount > 0 
                            ? $this->findAvailableVehicle($passengerCount, $scheduledDeparture, $scheduledArrival)
                            : null;
                        
                        // Auto-assign driver if vehicle was assigned
                        $driverId = $vehicleId 
                            ? $this->findAvailableDriver($scheduledDeparture, $scheduledArrival)
                            : null;
                        
                        // Auto-fetch flight_id from event team flights based on movement kind
                        $movementFlightId = null;
                        if ($plan->event_id && in_array($leg->leg_type, ['arrival', 'departure'])) {
                            $flight = \App\Models\TeamFlight::where('event_id', $plan->event_id)
                                ->where('team_id', $teamId)
                                ->where('direction', $leg->leg_type)
                                ->first();
                            if ($flight) {
                                $movementFlightId = $flight->id;
                            }
                        }
                        
                        // Auto-fetch accommodation_id from event team stay
                        $movementAccommodationId = null;
                        if (!empty($planData['accommodation_id'])) {
                            $movementAccommodationId = $planData['accommodation_id'];
                        } elseif ($plan->event_id && $teamId) {
                            $stay = \App\Models\TeamStay::where('event_id', $plan->event_id)
                                ->where('team_id', $teamId)
                                ->first();
                            if ($stay) {
                                $movementAccommodationId = $stay->id;
                            }
                        }
                        
                        Movement::create([
                            'code' => $this->generateMovementCode($plan),
                            'plan_id' => $plan->id,
                            'event_id' => $plan->event_id,
                            'team_id' => $teamId,
                            'flight_id' => $movementFlightId,
                            'accommodation_id' => $movementAccommodationId,
                            'checkpoint_template_id' => $leg->checkpoint_template_id,
                            'kind' => $leg->leg_type,
                            'functional_area' => $template->functional_area,
                            'from_location' => $leg->from_location,
                            'to_location' => $leg->to_location,
                            'window_start' => $scheduledDeparture,
                            'window_end' => $scheduledArrival,
                            'vehicle_id' => $vehicleId,
                            'driver_id' => $driverId,
                            'passengers' => $passengerCount,
                            'status' => 'scheduled',
                            'source' => 'template',
                        ]);
                    }
                }
            } else {
                // Single team mode: loop through legs
                foreach ($legs as $leg) {
                    $teamId = $teamAssignments[$leg->order] ?? $teamAssignments['default'] ?? null;
                    if (!$teamId) {
                        continue;
                    }

                    $team = Team::find($teamId);
                    $scheduledDeparture = null;
                    $scheduledArrival = null;
                    // A 'BUS' flight_number is a placeholder meaning the
                    // team travels by road, not an actual flight — no real
                    // reference time exists, so the window stays null and
                    // the sequential-timing fallback below must not kick in.
                    $isBusLeg = false;

                    // Smart timing based on leg type and team data — mirrors
                    // the bulk-mode loops so a departure leg always uses the
                    // team's departure flight, not a generic base time.
                    if ($leg->leg_type === 'arrival' && $plan->event_id) {
                        $arrivalFlight = \App\Models\TeamFlight::where('event_id', $plan->event_id)
                            ->where('team_id', $teamId)
                            ->where('direction', 'arrival')
                            ->first();

                        if ($arrivalFlight && $arrivalFlight->flight_number === 'BUS') {
                            $isBusLeg = true;
                        } elseif ($arrivalFlight && $arrivalFlight->scheduled_at) {
                            $offsetMinutes = $this->resolveOffsetMinutes($leg->checkpointTemplate?->checkpoints->first(), 'arrival', $plan->event_id);
                            $scheduledDeparture = $arrivalFlight->scheduled_at->copy()->addMinutes($offsetMinutes);
                            $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                        }
                    } elseif ($leg->leg_type === 'departure' && $plan->event_id) {
                        $departureFlight = \App\Models\TeamFlight::where('event_id', $plan->event_id)
                            ->where('team_id', $teamId)
                            ->where('direction', 'departure')
                            ->first();

                        if ($departureFlight && $departureFlight->flight_number === 'BUS') {
                            $isBusLeg = true;
                        } elseif ($departureFlight && $departureFlight->scheduled_at) {
                            $offsetMinutes = $this->resolveOffsetMinutes($leg->checkpointTemplate?->checkpoints->first(), 'departure', $plan->event_id);
                            $scheduledDeparture = $departureFlight->scheduled_at->copy()->addMinutes($offsetMinutes);
                            $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                        }
                    } elseif ($leg->leg_type === 'match' && $plan->event_id) {
                        $match = \App\Models\GameMatch::where('event_id', $plan->event_id)
                            ->where(function ($q) use ($teamId) {
                                $q->where('team1_id', $teamId)
                                  ->orWhere('team2_id', $teamId);
                            })
                            ->orderBy('kick_off')
                            ->first();

                        if ($match && $match->kick_off) {
                            $offsetMinutes = $this->resolveOffsetMinutes($leg->checkpointTemplate?->checkpoints->first(), 'match', $plan->event_id);
                            $scheduledDeparture = $match->kick_off->copy()->addMinutes($offsetMinutes);
                            $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                        }
                    } elseif ($leg->leg_type === 'transfer' && $plan->event_id) {
                        $arrivalFlight = \App\Models\TeamFlight::where('event_id', $plan->event_id)
                            ->where('team_id', $teamId)
                            ->where('direction', 'arrival')
                            ->first();

                        if ($arrivalFlight && $arrivalFlight->scheduled_at) {
                            $offsetMinutes = $this->resolveOffsetMinutes($leg->checkpointTemplate?->checkpoints->first(), 'transfer', $plan->event_id);
                            $scheduledDeparture = $arrivalFlight->scheduled_at->copy()->addMinutes($offsetMinutes);
                            $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                        }
                    }

                    // Fallback to sequential timing if smart timing not available
                    if (!$isBusLeg && (!$scheduledDeparture || !$scheduledArrival)) {
                        $scheduledDeparture = $currentTime->copy();
                        $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                    }

                    // BUS legs have no window, so they don't shift subsequent
                    // legs' sequential timing.
                    if ($scheduledArrival) {
                        $currentTime = $scheduledArrival->copy();
                    }

                    // Determine passenger count
                    $passengerCount = $team?->party_size_total ?? $leg->estimated_passengers ?? 0;
                    
                    // Auto-assign vehicle and driver
                    $vehicleId = $passengerCount > 0 
                        ? $this->findAvailableVehicle($passengerCount, $scheduledDeparture, $scheduledArrival)
                        : null;
                    
                    $driverId = $vehicleId 
                        ? $this->findAvailableDriver($scheduledDeparture, $scheduledArrival)
                        : null;
                    
                    // Auto-fetch flight_id
                    $movementFlightId = null;
                    if ($plan->event_id && in_array($leg->leg_type, ['arrival', 'departure'])) {
                        $flight = \App\Models\TeamFlight::where('event_id', $plan->event_id)
                            ->where('team_id', $teamId)
                            ->where('direction', $leg->leg_type)
                            ->first();
                        if ($flight) {
                            $movementFlightId = $flight->id;
                        }
                    }
                    
                    // Auto-fetch accommodation_id
                    $movementAccommodationId = null;
                    if ($plan->event_id && $teamId) {
                        $stay = \App\Models\TeamStay::where('event_id', $plan->event_id)
                            ->where('team_id', $teamId)
                            ->first();
                        if ($stay) {
                            $movementAccommodationId = $stay->id;
                        }
                    }
                    
                    Movement::create([
                        'code' => $this->generateMovementCode($plan),
                        'plan_id' => $plan->id,
                        'event_id' => $plan->event_id,
                        'team_id' => $teamId,
                        'flight_id' => $movementFlightId,
                        'accommodation_id' => $movementAccommodationId,
                        'checkpoint_template_id' => $leg->checkpoint_template_id,
                        'kind' => $leg->leg_type,
                        'functional_area' => $template->functional_area,
                        'from_location' => $leg->from_location,
                        'to_location' => $leg->to_location,
                        'window_start' => $scheduledDeparture,
                        'window_end' => $scheduledArrival,
                        'vehicle_id' => $vehicleId,
                        'driver_id' => $driverId,
                        'passengers' => $passengerCount,
                        'status' => 'scheduled',
                        'source' => 'template',
                    ]);
                }
            }

            // Calculate movement count and unique team count
            $movementCount = Movement::where('plan_id', $plan->id)->count();
            $uniqueTeams = Movement::where('plan_id', $plan->id)->distinct('team_id')->count('team_id');

            $plan->update([
                'movements_count' => $movementCount,
                'teams_count' => $uniqueTeams,
            ]);

            return $plan;
        });
    }

    /**
     * Generate a unique plan code.
     */
    protected function generatePlanCode($date): string
    {
        // Format: PLN-2026-0430-001 (Year-MonthDay-Sequence)
        $dateObj = is_string($date) ? new \DateTime($date) : $date;
        $year = $dateObj->format('Y');
        $monthDay = $dateObj->format('md');
        $prefix = "PLN-{$year}-{$monthDay}";
        
        // Find the next available number for this date (including soft-deleted)
        $latestPlan = Plan::withTrashed()
            ->where('code', 'like', "{$prefix}%")
            ->orderBy('code', 'desc')
            ->first();

        if ($latestPlan) {
            // Extract the sequence number from the end (after last dash)
            $parts = explode('-', $latestPlan->code);
            $lastNumber = (int) end($parts);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Generate code and ensure it's unique (handle race conditions)
        $attempts = 0;
        do {
            $code = sprintf('%s-%03d', $prefix, $nextNumber);
            $exists = Plan::withTrashed()->where('code', $code)->exists();
            
            if ($exists) {
                $nextNumber++;
                $attempts++;
                if ($attempts > 100) {
                    throw new \Exception("Unable to generate unique plan code after 100 attempts");
                }
            }
        } while ($exists);

        return $code;
    }

    /**
     * Generate a unique movement code.
     */
    protected function generateMovementCode(Plan $plan): string
    {
        // Generate globally unique movement code. Must derive the next
        // number from the highest CODE in use, not the highest row id —
        // once movements get deleted, id order and code order diverge (the
        // row with the highest id is not necessarily the row with the
        // highest M-number), so "latest by id + 1" can recompute a code
        // that's still held by an older, undeleted row and collide on the
        // unique constraint. Must also include soft-deleted rows
        // (withTrashed): Movement uses SoftDeletes, so a "deleted" movement's
        // code is still physically in the table and still enforced by the
        // DB's unique index, which doesn't know about deleted_at.
        $maxNumber = Movement::withTrashed()
            ->whereNotNull('code')
            ->selectRaw("MAX(CAST(SUBSTRING(code, 2) AS UNSIGNED)) as max_number")
            ->value('max_number');

        return sprintf('M%d', ($maxNumber ?? 0) + 1);
    }

    /**
     * Find an available vehicle with sufficient capacity.
     * Checks for time conflicts with existing movements.
     */
    protected function findAvailableVehicle(int $passengerCount, $scheduledDeparture, $scheduledArrival): ?int
    {
        // Find vehicles with sufficient capacity
        $vehicles = Vehicle::where('is_active', 1)
            ->where('capacity', '>=', $passengerCount)
            ->orderBy('capacity', 'asc') // Prefer smallest suitable vehicle
            ->get();

        foreach ($vehicles as $vehicle) {
            // Check if vehicle has conflicting movements at the same time
            $conflict = Movement::where('vehicle_id', $vehicle->id)
                ->where(function ($query) use ($scheduledDeparture, $scheduledArrival) {
                    $query->whereBetween('window_start', [$scheduledDeparture, $scheduledArrival])
                        ->orWhereBetween('window_end', [$scheduledDeparture, $scheduledArrival])
                        ->orWhere(function ($q) use ($scheduledDeparture, $scheduledArrival) {
                            $q->where('window_start', '<=', $scheduledDeparture)
                              ->where('window_end', '>=', $scheduledArrival);
                        });
                })
                ->exists();

            if (!$conflict) {
                return $vehicle->id;
            }
        }

        return null; // No available vehicle found
    }

    /**
     * Find an available driver.
     * Checks for time conflicts with existing movements.
     */
    protected function findAvailableDriver($scheduledDeparture, $scheduledArrival): ?int
    {
        $drivers = Driver::whereIn('status', ['available', 'on_shift'])->get();

        foreach ($drivers as $driver) {
            // Check if driver has conflicting movements at the same time
            $conflict = Movement::where('driver_id', $driver->id)
                ->where(function ($query) use ($scheduledDeparture, $scheduledArrival) {
                    $query->whereBetween('window_start', [$scheduledDeparture, $scheduledArrival])
                        ->orWhereBetween('window_end', [$scheduledDeparture, $scheduledArrival])
                        ->orWhere(function ($q) use ($scheduledDeparture, $scheduledArrival) {
                            $q->where('window_start', '<=', $scheduledDeparture)
                              ->where('window_end', '>=', $scheduledArrival);
                        });
                })
                ->exists();

            if (!$conflict) {
                return $driver->id;
            }
        }

        return null; // No available driver found
    }
}

