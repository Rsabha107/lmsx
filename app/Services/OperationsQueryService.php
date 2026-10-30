<?php

namespace App\Services;

use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\User;
use Carbon\Carbon;
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

    /**
     * Movements starting on one calendar day (cancelled ones left out). Planned
     * movements count even before their job is generated; "job_generated" says
     * which have one. A user who can view movements sees them like the Planning
     * page; one who can only view jobs gets the jobs they may see, like the Day
     * Board (without "view unassigned" only the jobs they supervise).
     *
     * @return array{date: string, total: int, movements: array<int, array<string, mixed>>}
     */
    public function getMovementsByDate(int $eventId, User $user, string $date, ?string $team = null, int $limit = 100): array
    {
        $day = Carbon::parse($date)->toDateString();
        $teamFilter = fn ($q) => $q->whereHas('team', fn ($t) => $t->where('team_name', 'like', '%'.$team.'%'));

        if ($user->can('movements.view')) {
            $query = Movement::query()
                ->where('event_id', $eventId)
                ->where('status', '!=', 'cancelled')
                ->whereDoesntHave('job', fn ($q) => $q->where('status', 'cancelled'))
                ->whereDate('window_start', $day)
                ->when(! $user->can('movements.view-all-functional-areas'), fn ($q) => $q->whereIn('functional_area', $user->functionalAreaCodes()))
                ->when($team, $teamFilter)
                ->with(['team', 'job'])
                ->orderBy('window_start');

            return [
                'date' => $day,
                'total' => (clone $query)->count(),
                'movements' => $query->limit($limit)->get()->map(fn (Movement $m) => $this->summarizeMovement($m))->all(),
            ];
        }

        if (! $user->can('jobs.view')) {
            return ['date' => $day, 'total' => 0, 'movements' => []];
        }

        $query = $this->scopedJobsQuery($eventId, $user)
            ->where('jobs_operations.status', '!=', 'cancelled')
            ->whereDate('movements.window_start', $day)
            ->when(! $user->can('jobs.view-unassigned'), fn ($q) => $q->supervisedBy($user->id, 'jobs_operations.'))
            ->when($team, $teamFilter)
            ->orderBy('movements.window_start');

        return [
            'date' => $day,
            'total' => (clone $query)->count(),
            'movements' => $query->limit($limit)->get()->map(fn (JobOperation $job) => $this->summarizeJob($job))->all(),
        ];
    }

    private function summarizeMovement(Movement $movement): array
    {
        $job = $movement->job;

        return [
            'id' => $job?->job_id,
            'movement_id' => $movement->id,
            'movement_code' => $movement->code,
            'team' => $movement->team?->team_name,
            'functional_area' => $movement->functional_area,
            'kind' => $movement->kind,
            'from' => $movement->from_location,
            'to' => $movement->to_location,
            'window_start' => $movement->window_start?->format('H:i'),
            'window_end' => $movement->window_end?->format('H:i'),
            'delay_minutes' => $movement->delay_minutes,
            'job_generated' => $job !== null,
            'status' => $job?->status ?? 'no job yet ('.$movement->status.')',
            'checkpoints_completed' => $job?->checkpoints_completed,
            'checkpoints_total' => $job?->checkpoints_total,
            'url' => self::movementUrl($movement),
        ];
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

    /** Movement id from a numeric id, a movement code (TRP-00003) or a job reference (JOB-...). */
    public function resolveMovementId(string $reference, int $eventId): ?int
    {
        $reference = trim($reference);

        if (ctype_digit($reference)) {
            return (int) $reference;
        }

        return Movement::where('event_id', $eventId)->where('code', $reference)->value('id')
            ?? JobOperation::where('event_id', $eventId)->where('job_id', $reference)->value('movement_id');
    }

    /** Job id from a numeric id, a job reference (JOB-...) or the code of its movement (TRP-...). */
    public function resolveJobId(string $reference, int $eventId): ?int
    {
        $reference = trim($reference);

        if (ctype_digit($reference)) {
            return (int) $reference;
        }

        return JobOperation::where('event_id', $eventId)->where('job_id', $reference)->value('id')
            ?? JobOperation::where('event_id', $eventId)
                ->whereHas('movement', fn ($q) => $q->where('code', $reference))
                ->value('id');
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
            'movement_code' => $movement->code,
            'job_id' => $job?->job_id,
            'team' => $movement->team?->team_name,
            'kind' => $movement->kind,
            'functional_area' => $movement->functional_area,
            'from' => $movement->from_location,
            'to' => $movement->to_location,
            'window_start' => $movement->window_start?->format('Y-m-d H:i'),
            'window_end' => $movement->window_end?->format('Y-m-d H:i'),
            'delay_minutes' => $movement->delay_minutes,
            'status' => $job?->status,
            'url' => self::movementUrl($movement),
            'checkpoints' => $job?->checkpoints->map(fn ($cp) => [
                'name' => $cp->name,
                'state' => $cp->state,
                'scheduled_at' => $cp->scheduled_at?->format('H:i'),
                'completed_at' => $cp->completed_at?->format('H:i'),
                'is_on_time' => $cp->is_on_time,
                'punctuality' => $cp->state !== 'done' ? null : match ($cp->is_on_time) {
                    true => 'on time',
                    false => 'late',
                    null => 'not judged (no scheduled time)',
                },
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

        // Daleel works on the active event only (the job policy also allows other accessible events).
        if (! $job || (int) $job->event_id !== (int) session('active_event_id') || Gate::forUser($user)->denies('view', $job)) {
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
            'url' => self::jobUrl($job),
            'team' => $job->team?->team_name,
            'status' => $job->status,
            'total_delay_minutes' => $totalDelayMinutes,
            'checkpoint_variances' => $checkpointVariances->all(),
            'largest_contributor' => $largestContributor,
            // Positive = some of the largest single-checkpoint delay was
            // made up by the time the movement's overall delay was measured.
            'recovered_minutes' => $largestContributor
                ? max(0, $largestContributor['variance_minutes'] - $totalDelayMinutes)
                : 0,
            'next_checkpoint' => $this->nextCheckpoint($job),
        ];
    }

    /**
     * The first step still to do, and whether it is overdue (its time has passed), at risk (the job is
     * running late at its last done step, so it is expected that late unless time is made up) or on track.
     */
    private function nextCheckpoint(JobOperation $job): ?array
    {
        $steps = $job->checkpoints->sortBy('order')->values();
        $next = $steps->first(fn (JobCheckpoint $cp) => in_array($cp->state, ['pending', 'active'], true));
        if (! $next || in_array($job->status, ['completed', 'cancelled'], true)) {
            return null;
        }

        $lastDone = $steps->filter(fn (JobCheckpoint $cp) => $cp->state === 'done' && $cp->scheduled_at && $cp->completed_at)->last();
        $runningLate = $lastDone ? max(0, (int) $lastDone->delay_minutes) : 0;
        $dueIn = $next->scheduled_at ? (int) round(now()->diffInMinutes($next->scheduled_at, false)) : null;

        return [
            'name' => $next->name,
            'scheduled_at' => $next->scheduled_at?->format('H:i'),
            'due_in_minutes' => $dueIn,
            'running_late_minutes' => $runningLate,
            'expected_at' => $next->scheduled_at && $runningLate ? $next->scheduled_at->copy()->addMinutes($runningLate)->format('H:i') : null,
            'state' => match (true) {
                $dueIn === null => 'not timed',
                $dueIn < 0 => 'overdue',
                $runningLate > 0 => 'at risk',
                default => 'on track',
            },
            'basis' => match (true) {
                $dueIn === null => 'The step has no scheduled time.',
                $dueIn < 0 => 'Scheduled '.$next->scheduled_at->format('H:i').', '.abs($dueIn).' min ago, still not done.',
                $runningLate > 0 => "The last done step, \"{$lastDone->name}\", was {$runningLate} min late; at that pace this step lands at ".$next->scheduled_at->copy()->addMinutes($runningLate)->format('H:i').'.',
                default => 'Due in '.$dueIn.' min and the job is not running late.',
            },
            'steps_left' => $steps->whereIn('state', ['pending', 'active'])->count(),
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
            'movement_code' => $movement?->code,
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
            'url' => self::jobUrl($job),
        ];
    }

    /** In-app links Daleel may put in an answer (MarkdownAnswer only renders these paths as links). */
    public static function jobUrl(JobOperation $job): string
    {
        return '/job/'.$job->id;
    }

    public static function movementUrl(Movement $movement): string
    {
        return $movement->job ? self::jobUrl($movement->job) : '/plans?plan='.$movement->plan_id.'&movement='.$movement->id;
    }
}
