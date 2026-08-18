<?php

namespace App\Services;

use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Safe, read-only data access for the AI Copilot. Every method here is a
 * pre-approved "tool" the LLM can call — it never sees SQL or a DB
 * connection, only these fixed, Policy-checked, event-scoped methods.
 *
 * A failed Policy check returns an empty result, never an "unauthorized"
 * marker — functional-area boundaries shouldn't be discoverable via how
 * the AI phrases its answer.
 */
class OperationsQueryService
{
    public function getActiveMovements(int $eventId, User $user): array
    {
        if (! $user->can('jobs.view')) {
            return [];
        }

        return $this->scopedJobsQuery($eventId, $user)
            ->where('jobs_operations.status', 'in-progress')
            ->orderBy('movements.window_start')
            ->get()
            ->map(fn (JobOperation $job) => $this->summarizeJob($job))
            ->all();
    }

    public function getDelayedMovements(int $eventId, User $user): array
    {
        if (! $user->can('jobs.view')) {
            return [];
        }

        return $this->scopedJobsQuery($eventId, $user)
            ->where('movements.delay_minutes', '>', 0)
            ->orderByDesc('movements.delay_minutes')
            ->get()
            ->map(fn (JobOperation $job) => $this->summarizeJob($job))
            ->all();
    }

    public function getUpcomingMovements(int $eventId, User $user, int $withinMinutes = 120): array
    {
        if (! $user->can('jobs.view')) {
            return [];
        }

        $now = now();

        return $this->scopedJobsQuery($eventId, $user)
            ->whereBetween('movements.window_start', [$now, $now->clone()->addMinutes($withinMinutes)])
            ->orderBy('movements.window_start')
            ->get()
            ->map(fn (JobOperation $job) => $this->summarizeJob($job))
            ->all();
    }

    public function getJobStatusSummary(int $eventId, User $user): array
    {
        if (! $user->can('jobs.view')) {
            return [];
        }

        return $this->scopedJobsQuery($eventId, $user)
            ->get()
            ->groupBy('status')
            ->map->count()
            ->all();
    }

    public function getMovementDetails(int $movementId, User $user): ?array
    {
        $movement = Movement::with(['team', 'job.checkpoints'])->find($movementId);

        if (! $movement || Gate::forUser($user)->denies('view', $movement)) {
            return null;
        }

        $job = $movement->job;

        return [
            'id' => $movement->id,
            'team' => $movement->team?->team_name,
            'kind' => $movement->kind,
            'functional_area' => $movement->functional_area,
            'from' => $movement->from_location,
            'to' => $movement->to_location,
            'window_start' => $movement->window_start?->format('Y-m-d H:i'),
            'window_end' => $movement->window_end?->format('Y-m-d H:i'),
            'delay_minutes' => $movement->delay_minutes,
            'status' => $job?->status,
            'checkpoints' => $job?->checkpoints->map(fn ($cp) => [
                'name' => $cp->name,
                'state' => $cp->state,
                'scheduled_at' => $cp->scheduled_at?->format('H:i'),
                'completed_at' => $cp->completed_at?->format('H:i'),
                'is_on_time' => $cp->is_on_time,
            ])->all() ?? [],
        ];
    }

    /**
     * Jobs that are active but have a checkpoint that's overdue (scheduled
     * to have happened, still pending/active) — reuses the same
     * `JobCheckpoint::scopeOverdue()` already used by the model, rather
     * than re-deriving "overdue" a second time.
     */
    public function getMissingUpdates(int $eventId, User $user): array
    {
        if (! $user->can('jobs.view')) {
            return [];
        }

        return $this->scopedJobsQuery($eventId, $user)
            ->whereIn('jobs_operations.status', ['dispatched', 'in-progress'])
            ->whereHas('checkpoints', fn ($q) => $q->overdue())
            ->with(['checkpoints' => fn ($q) => $q->overdue()])
            ->get()
            ->map(function (JobOperation $job) {
                $summary = $this->summarizeJob($job);
                $oldestOverdue = $job->checkpoints->first();

                $summary['overdue_checkpoint'] = $oldestOverdue?->name;
                $summary['overdue_since_scheduled'] = $oldestOverdue?->scheduled_at?->format('H:i');
                // scopeOverdue() already guarantees scheduled_at < now(), so the
                // direction is unambiguous — but diffInMinutes() defaults to signed
                // in Carbon 3 (relative to the argument, not $this), so the bare
                // call would silently return a negative number here. Same footgun
                // already documented on JobCheckpoint::markAsDone().
                $summary['overdue_minutes'] = $oldestOverdue?->scheduled_at
                    ? now()->diffInMinutes($oldestOverdue->scheduled_at, true)
                    : null;

                return $summary;
            })
            ->all();
    }

    /**
     * Checkpoint-by-checkpoint variance breakdown for one job, to answer
     * "why is this late". All figures are deterministic (scheduled vs
     * actual times, reusing JobCheckpoint's existing delay_minutes
     * accessor) — the AI only narrates what this method already computed,
     * it never derives the numbers itself.
     */
    public function explainDelay(int $jobId, User $user): ?array
    {
        $job = JobOperation::with(['movement', 'team', 'checkpoints'])->find($jobId);

        if (! $job || Gate::forUser($user)->denies('view', $job)) {
            return null;
        }

        $checkpointVariances = $job->checkpoints
            ->filter(fn (JobCheckpoint $cp) => $cp->state === 'done' && $cp->scheduled_at && $cp->completed_at)
            ->map(fn (JobCheckpoint $cp) => [
                'name' => $cp->name,
                'scheduled_at' => $cp->scheduled_at->format('H:i'),
                'completed_at' => $cp->completed_at->format('H:i'),
                // Signed: positive = late, negative = early. Reuses
                // JobCheckpoint::getDelayMinutesAttribute() rather than
                // re-deriving the diffInMinutes-direction logic a third time.
                'variance_minutes' => $cp->delay_minutes,
            ])
            ->values();

        $largestContributor = $checkpointVariances->sortByDesc('variance_minutes')->first();
        $totalDelayMinutes = $job->movement?->delay_minutes ?? 0;

        return [
            'job_id' => $job->job_id ?? 'J-'.$job->id,
            'team' => $job->team?->team_name,
            'total_delay_minutes' => $totalDelayMinutes,
            'checkpoint_variances' => $checkpointVariances->all(),
            'largest_contributor' => $largestContributor,
            // Positive = some of the largest single-checkpoint delay was
            // made up by the time the movement's overall delay was measured.
            'recovered_minutes' => $largestContributor
                ? max(0, $largestContributor['variance_minutes'] - $totalDelayMinutes)
                : 0,
        ];
    }

    /**
     * Average variance per checkpoint name across the event, from stored
     * (not recomputed) delay_minutes values — identifies which step in
     * the process tends to run late.
     */
    public function getCheckpointPerformance(int $eventId, User $user): array
    {
        if (! $user->can('jobs.view')) {
            return [];
        }

        $jobIds = $this->scopedJobsQuery($eventId, $user)->pluck('jobs_operations.id');

        return JobCheckpoint::query()
            ->whereIn('job_id', $jobIds)
            ->where('state', 'done')
            ->whereNotNull('delay_minutes')
            ->selectRaw('name, COUNT(*) as sample_count, ROUND(AVG(delay_minutes), 1) as avg_delay_minutes')
            ->groupBy('name')
            ->orderByDesc('avg_delay_minutes')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'sample_count' => (int) $row->sample_count,
                'avg_delay_minutes' => (float) $row->avg_delay_minutes,
            ])
            ->all();
    }

    /**
     * The base query every job-level tool shares: scoped to the given
     * event, and to the user's functional area(s) unless they hold the
     * "-all-functional-areas" permission.
     */
    private function scopedJobsQuery(int $eventId, User $user)
    {
        $query = JobOperation::query()
            ->join('movements', 'jobs_operations.movement_id', '=', 'movements.id')
            ->where('jobs_operations.event_id', $eventId)
            ->with(['team', 'movement', 'vehicle'])
            ->select('jobs_operations.*');

        if (! $user->can('jobs.view-all-functional-areas')) {
            $query->whereIn('jobs_operations.functional_area', $user->functionalAreaCodes());
        }

        return $query;
    }

    private function summarizeJob(JobOperation $job): array
    {
        $movement = $job->movement;
        $team = $job->team;

        return [
            'id' => $job->job_id ?? 'J-'.$job->id,
            'movement_id' => $movement?->id,
            'team' => $team?->team_name,
            'functional_area' => $job->functional_area,
            'kind' => $movement?->kind,
            'from' => $movement?->from_location,
            'to' => $movement?->to_location,
            'window_start' => $movement?->window_start?->format('H:i'),
            'window_end' => $movement?->window_end?->format('H:i'),
            'delay_minutes' => $movement?->delay_minutes,
            'status' => $job->status,
            'checkpoints_completed' => $job->checkpoints_completed,
            'checkpoints_total' => $job->checkpoints_total,
        ];
    }
}
