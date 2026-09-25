<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Single owner of job and checkpoint lifecycle changes.
 *
 * The web, legacy and mobile controllers previously each had their own copy of
 * this logic, and they disagreed on evidence storage, on-time calculation and
 * auto-transition rules. Everything now funnels through here so a change lands
 * in one place, inside a transaction, with an audit entry.
 */
class JobLifecycleService
{
    /** States a checkpoint can no longer be completed or skipped out of. */
    private const RESOLVED_STATES = ['done', 'skipped'];

    public function __construct(private readonly CheckpointUploadService $uploads)
    {
    }

    /**
     * Move a job to a new status, stamping the matching timestamp and mirroring
     * the reality onto its movement.
     *
     * @throws RuntimeException when the transition is not permitted
     */
    public function transitionStatus(JobOperation $job, string $to, ?User $actor = null): JobOperation
    {
        if (! in_array($to, JobOperation::STATUSES, true)) {
            throw new RuntimeException("Unknown job status '{$to}'.");
        }

        if ($job->status === $to) {
            return $job;
        }

        return DB::transaction(function () use ($job, $to) {
            // Re-read under a row lock: two operators pressing Complete at once
            // must not both pass the checks below.
            $job->newQuery()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();
            $job->refresh();

            $from = $job->status;

            if ($from === $to) {
                return $job;
            }

            if (! $job->canTransitionTo($to)) {
                $allowed = JobOperation::TRANSITIONS[$from] ?? [];

                throw new RuntimeException($allowed === []
                    ? "Job {$job->job_id} is {$from} and can no longer change status."
                    : "Cannot move job {$job->job_id} from {$from} to {$to}. Allowed: ".implode(', ', $allowed).'.');
            }

            if ($to === 'completed') {
                $this->assertCheckpointsResolved($job);
            }

            $attrs = ['status' => $to];

            $timestampColumn = JobOperation::STATUS_TIMESTAMPS[$to] ?? null;
            if ($timestampColumn && ! $job->{$timestampColumn}) {
                $attrs[$timestampColumn] = now();
            }

            // Undoing an accidental start clears the evidence that it ever ran.
            $isRevertToPending = $from === 'in-progress' && $to === 'pending';
            if ($isRevertToPending) {
                $attrs['started_at'] = null;
            }

            $job->update($attrs);
            $this->syncMovementTimes($job, $to, $isRevertToPending);

            AuditLog::record(
                action: match (true) {
                    $to === 'dispatched' => 'Job dispatched',
                    $to === 'in-progress' => 'Job started',
                    $to === 'completed' => 'Job completed',
                    $to === 'cancelled' => 'Job cancelled',
                    $isRevertToPending => 'Job reverted to scheduled',
                    default => 'Job status changed',
                },
                target: $job->job_id.($job->team ? ' · '.$job->team->team_name : ''),
                meta: "{$from} → {$to}",
                subject: $job,
                eventId: $job->event_id,
            );

            return $job;
        });
    }

    /**
     * A job is only finished once every required checkpoint has been resolved.
     * A skip counts as resolved — it is a deliberate, audited decision, and
     * treating it otherwise would strand the job permanently.
     */
    private function assertCheckpointsResolved(JobOperation $job): void
    {
        $outstanding = $job->checkpoints()
            ->where('is_required', true)
            ->whereNotIn('state', self::RESOLVED_STATES)
            ->count();

        if ($outstanding > 0) {
            throw new RuntimeException(
                "Job {$job->job_id} cannot be completed: {$outstanding} required checkpoint"
                .($outstanding === 1 ? ' is' : 's are').' still outstanding.'
            );
        }
    }

    /**
     * Complete a checkpoint with optional evidence, then pull the parent job
     * along if this was its first or last outstanding checkpoint.
     *
     * @param  array{
     *     actual_time?: ?string, notes?: ?string, photo?: ?UploadedFile,
     *     signature?: ?string, planned_bags?: ?int, bags_loaded?: ?int,
     *     food_bags?: ?int, oversized_pieces?: ?int,
     *     gps_latitude?: ?float, gps_longitude?: ?float, exclude_date?: bool
     * }  $data
     *
     * @throws RuntimeException when the checkpoint is already finished
     */
    public function completeCheckpoint(
        JobCheckpoint $checkpoint,
        User $actor,
        array $data = [],
        string $method = 'web'
    ): JobCheckpoint {
        // Cheap rejection; the authoritative check runs under the row lock.
        $this->assertOutstanding($checkpoint);

        $stored = [];

        try {
            return DB::transaction(function () use (&$stored, $checkpoint, $actor, $data, $method) {
                $this->lockCheckpoint($checkpoint);
                $this->assertOutstanding($checkpoint);

                $completedAt = $this->resolveCompletedAt(
                    $checkpoint,
                    $data['actual_time'] ?? null,
                    $data['exclude_date'] ?? false
                );

                $attrs = [
                    'state' => 'done',
                    'completed_by' => $actor->id,
                    'completion_method' => $method,
                    'completed_at' => $completedAt,
                    'actual_duration_seconds' => $this->resolveDuration($checkpoint, $completedAt),
                ];

                if (array_key_exists('notes', $data)) {
                    $attrs['notes'] = $data['notes'];
                }

                foreach (['planned_bags', 'bags_loaded', 'food_bags', 'oversized_pieces', 'gps_latitude', 'gps_longitude'] as $field) {
                    if (array_key_exists($field, $data) && $data[$field] !== null) {
                        $attrs[$field] = $data[$field];
                    }
                }

                $attrs += $this->resolvePunctuality($checkpoint, $completedAt, $data['exclude_date'] ?? false);

                $evidence = $this->storeEvidence($checkpoint, $data);
                $stored = array_values($evidence);
                $attrs += $evidence;

                $this->assertEvidenceSatisfied($checkpoint, $attrs);

                $checkpoint->update($attrs);

                $this->syncJobProgress($checkpoint->job);

                AuditLog::record(
                    action: 'Checkpoint completed',
                    target: ($checkpoint->job?->job_id ?? 'JOB').' · '.$checkpoint->name,
                    meta: $method.($attrs['is_on_time'] ?? true ? '' : ' · late'),
                    subject: $checkpoint->job,
                    eventId: $checkpoint->job?->event_id,
                );

                return $checkpoint->fresh();
            });
        } catch (Throwable $e) {
            // The row never committed, so the uploads it referenced are orphans.
            $this->discardEvidence($stored);

            throw $e;
        }
    }

    /**
     * Mark a checkpoint as no longer applicable. Skipped checkpoints stay in the
     * denominator, so the parent job never auto-completes off the back of one.
     */
    public function skipCheckpoint(
        JobCheckpoint $checkpoint,
        User $actor,
        string $reason,
        ?string $exceptionType = null,
        ?string $notes = null
    ): JobCheckpoint {
        $this->assertOutstanding($checkpoint);

        return DB::transaction(function () use ($checkpoint, $actor, $reason, $exceptionType, $notes) {
            $this->lockCheckpoint($checkpoint);
            $this->assertOutstanding($checkpoint);

            $checkpoint->update([
                'state' => 'skipped',
                'skip_reason' => $reason,
                'skipped_by' => $actor->id,
                'skipped_at' => now(),
                'exception_type' => $exceptionType,
                'notes' => $notes,
            ]);

            $this->syncJobProgress($checkpoint->job, autoComplete: false);

            AuditLog::record(
                action: 'Checkpoint skipped',
                target: ($checkpoint->job?->job_id ?? 'JOB').' · '.$checkpoint->name,
                meta: $reason,
                subject: $checkpoint->job,
                eventId: $checkpoint->job?->event_id,
            );

            return $checkpoint->fresh();
        });
    }

    /**
     * Supervisor override: force a checkpoint to done or skipped, recording who
     * forced it and why. Shares the timestamp, duration and punctuality rules
     * with a normal completion so overridden rows stay comparable.
     *
     * @param  array{
     *     state: string, reason: string, notes?: ?string, actual_time?: ?string,
     *     exclude_date?: bool, photo?: UploadedFile|string|null, signature?: ?string,
     *     planned_bags?: ?int, bags_loaded?: ?int, food_bags?: ?int, oversized_pieces?: ?int,
     *     driver_id?: ?int, supervisor_id?: ?int
     * }  $data
     */
    public function overrideCheckpoint(JobCheckpoint $checkpoint, User $actor, array $data): JobCheckpoint
    {
        $state = $data['state'];

        if (! in_array($state, ['done', 'skipped', 'missed'], true)) {
            throw new RuntimeException("Cannot override a checkpoint to '{$state}'.");
        }

        $stored = [];

        try {
            return DB::transaction(function () use (&$stored, $checkpoint, $actor, $data, $state) {
                $this->lockCheckpoint($checkpoint);

                $attrs = [
                    'state' => $state === 'missed' ? 'skipped' : $state,
                    'was_overridden' => true,
                    'override_reason' => $data['reason'],
                    'override_notes' => $data['notes'] ?? null,
                    'overridden_by' => $actor->id,
                    'overridden_at' => now(),
                    'notes' => $data['notes'] ?? null,
                ];

                if ($state === 'done') {
                    $excludeDate = (bool) ($data['exclude_date'] ?? false);
                    $completedAt = $this->resolveCompletedAt($checkpoint, $data['actual_time'] ?? null, $excludeDate);

                    $attrs += [
                        'override_actual_time' => $data['actual_time'] ?? null,
                        'completed_at' => $completedAt,
                        'completed_by' => $actor->id,
                        'completion_method' => 'web',
                        'actual_duration_seconds' => $this->resolveDuration($checkpoint, $completedAt),
                    ];

                    foreach (['planned_bags', 'bags_loaded', 'food_bags', 'oversized_pieces'] as $field) {
                        if (array_key_exists($field, $data) && $data[$field] !== null) {
                            $attrs[$field] = $data[$field];
                        }
                    }

                    $attrs += $this->resolvePunctuality($checkpoint, $completedAt, $excludeDate);

                    $evidence = $this->storeEvidence($checkpoint, $data);
                    $stored = array_values($evidence);
                    $attrs += $evidence;
                } else {
                    $attrs += [
                        'skip_reason' => $data['reason'],
                        'skipped_by' => $actor->id,
                        'skipped_at' => now(),
                    ];

                    if ($state === 'missed') {
                        $attrs['exception_type'] = 'missed';
                    }
                }

                $checkpoint->update($attrs);

                // A forced skip must not tip the job into completed on its own.
                $this->syncJobProgress($checkpoint->job, autoComplete: $state === 'done');

                AuditLog::record(
                    action: 'Checkpoint overridden',
                    target: ($checkpoint->job?->job_id ?? 'JOB').' · '.$checkpoint->name,
                    meta: $state.' · '.$data['reason'],
                    subject: $checkpoint->job,
                    eventId: $checkpoint->job?->event_id,
                );

                if ($checkpoint->job) {
                    $this->reassignCrew($checkpoint->job, $data['driver_id'] ?? null, $data['supervisor_id'] ?? null, $data['reason']);
                }

                return $checkpoint->fresh();
            });
        } catch (Throwable $e) {
            $this->discardEvidence($stored);

            throw $e;
        }
    }

    /**
     * Change a job's crew without touching any checkpoint.
     *
     * @return bool whether anything actually changed
     */
    public function changeCrew(JobOperation $job, ?int $driverId, ?int $supervisorId, ?string $reason): bool
    {
        return DB::transaction(fn () => $this->reassignCrew($job, $driverId, $supervisorId, $reason));
    }

    /**
     * Swap the driver and/or supervisor on a job, mirroring the change onto its
     * movement so Planning shows the same crew. A null id leaves that role as is.
     */
    private function reassignCrew(JobOperation $job, ?int $driverId, ?int $supervisorId, ?string $reason): bool
    {
        $changes = [];

        if ($driverId !== null && $driverId !== $job->driver_id) {
            $from = $job->driver?->name ?? 'Unassigned';
            $job->driver_id = $driverId;
            $changes[] = "Driver {$from} → " . (Driver::find($driverId)?->name ?? "#{$driverId}");
        }

        if ($supervisorId !== null && $supervisorId !== $job->supervisor_id) {
            $from = $job->supervisor?->name ?? 'Unassigned';
            $job->supervisor_id = $supervisorId;
            $changes[] = "Supervisor {$from} → " . (User::find($supervisorId)?->name ?? "#{$supervisorId}");
        }

        if ($changes === []) {
            return false;
        }

        $job->save();
        $job->movement?->update([
            'driver_id' => $job->driver_id,
            'field_supervisor_id' => $job->supervisor_id,
        ]);

        AuditLog::record(
            action: 'Job crew changed',
            target: $job->job_id ?? 'JOB',
            meta: implode('; ', $changes) . ' · ' . ($reason ?: 'no reason given'),
            subject: $job,
            eventId: $job->event_id,
        );

        return true;
    }

    /**
     * Recount progress and move the job along if the checkpoints imply it.
     */
    public function syncJobProgress(?JobOperation $job, bool $autoComplete = true): void
    {
        if (! $job) {
            return;
        }

        $job->updateProgress();
        $job->refresh();

        if ($job->status === 'pending' || $job->status === 'dispatched') {
            $this->silentlyTransition($job, 'in-progress');

            return;
        }

        if ($autoComplete
            && $job->status === 'in-progress'
            && $job->checkpoints_total > 0
            && $job->checkpoints_completed === $job->checkpoints_total) {
            $this->silentlyTransition($job, 'completed');
        }
    }

    /**
     * Automatic transitions must never abort the caller's work, so an illegal
     * jump or an unmet completion invariant here is ignored rather than thrown.
     */
    private function silentlyTransition(JobOperation $job, string $to): void
    {
        if (! $job->canTransitionTo($to)) {
            return;
        }

        try {
            $this->transitionStatus($job, $to);
        } catch (RuntimeException) {
            // The checkpoints don't support it yet; the caller's own work stands.
        }
    }

    /**
     * Take a row lock on the checkpoint and re-read it, so concurrent writers
     * queue up rather than both acting on stale state.
     */
    private function lockCheckpoint(JobCheckpoint $checkpoint): void
    {
        $checkpoint->newQuery()->whereKey($checkpoint->getKey())->lockForUpdate()->firstOrFail();
        $checkpoint->refresh();
    }

    private function assertOutstanding(JobCheckpoint $checkpoint): void
    {
        if (! in_array($checkpoint->state, self::RESOLVED_STATES, true)) {
            return;
        }

        throw new RuntimeException($checkpoint->state === 'done'
            ? 'Checkpoint already completed.'
            : 'Checkpoint was skipped and can no longer be changed.');
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function discardEvidence(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    private function syncMovementTimes(JobOperation $job, string $to, bool $isRevertToPending): void
    {
        $movement = $job->movement;

        if (! $movement) {
            return;
        }

        if ($to === 'in-progress' && ! $movement->actual_departure) {
            $movement->update(['actual_departure' => now()]);
        } elseif ($to === 'completed' && ! $movement->actual_arrival) {
            $movement->update(['actual_arrival' => now()]);
        } elseif ($isRevertToPending) {
            $movement->update(['actual_departure' => null]);
        }
    }

    /**
     * @return array{photo_path?: string, signature_path?: string}
     */
    private function storeEvidence(JobCheckpoint $checkpoint, array $data): array
    {
        $attrs = [];

        $photo = $data['photo'] ?? null;
        if ($photo instanceof UploadedFile) {
            $attrs['photo_path'] = $this->uploads->storePhoto($photo, $checkpoint->id, $checkpoint->job_id);
        } elseif (is_string($photo) && $photo !== '') {
            $stored = $this->uploads->storeBase64($photo, 'photo', 'photos', $checkpoint->id, $checkpoint->job_id);
            if ($stored) {
                $attrs['photo_path'] = $stored;
            }
        }

        if (! empty($data['signature'])) {
            $signature = $this->uploads->storeSignature($data['signature'], $checkpoint->id, $checkpoint->job_id);
            if ($signature) {
                $attrs['signature_path'] = $signature;
            }
        }

        return $attrs;
    }

    /**
     * A compliance checkpoint is only complete once its artifact exists. An
     * override deliberately bypasses this, which is why it demands a reason.
     *
     * @param  array{photo_path?: string, signature_path?: string}  $attrs
     */
    private function assertEvidenceSatisfied(JobCheckpoint $checkpoint, array $attrs): void
    {
        $missing = [];

        if ($checkpoint->requires_photo && ! ($attrs['photo_path'] ?? $checkpoint->photo_path)) {
            $missing[] = 'a photo';
        }

        if ($checkpoint->requires_signature && ! ($attrs['signature_path'] ?? $checkpoint->signature_path)) {
            $missing[] = 'a signature';
        }

        if ($missing !== []) {
            throw new RuntimeException(
                "Checkpoint \"{$checkpoint->name}\" requires ".implode(' and ', $missing).'.'
            );
        }
    }

    /**
     * A supervisor confirming a late-evening checkpoint just after midnight means
     * the next day, not 24 hours in the past.
     */
    private function resolveCompletedAt(JobCheckpoint $checkpoint, ?string $actualTime, bool $excludeDate): Carbon
    {
        if (! $actualTime) {
            return now();
        }

        [$hours, $minutes] = array_map('intval', explode(':', $actualTime));

        $base = $excludeDate && $checkpoint->scheduled_at
            ? $checkpoint->scheduled_at->copy()
            : Carbon::today();

        $completedAt = $base->copy()->setTime($hours, $minutes);

        if ($checkpoint->scheduled_at && $checkpoint->scheduled_at->hour >= 18 && $hours < 6) {
            $completedAt->addDay();
        }

        return $completedAt;
    }

    private function resolveDuration(JobCheckpoint $checkpoint, Carbon $completedAt): ?int
    {
        // Against scheduled_at this is really a variance rather than a duration,
        // but that is what every caller has always recorded here.
        if ($checkpoint->scheduled_at) {
            return (int) abs($completedAt->diffInSeconds($checkpoint->scheduled_at));
        }

        if ($checkpoint->started_at) {
            return (int) $completedAt->diffInSeconds($checkpoint->started_at, true);
        }

        if ($checkpoint->estimated_minutes) {
            return (int) $checkpoint->estimated_minutes * 60;
        }

        return null;
    }

    /**
     * Punctuality is judged against the movement's window, not the checkpoint's
     * own scheduled time, so a whole job is late or not as a unit.
     *
     * @return array{is_on_time?: bool, delay_minutes?: int}
     */
    private function resolvePunctuality(JobCheckpoint $checkpoint, Carbon $completedAt, bool $excludeDate): array
    {
        $windowEnd = $checkpoint->job?->movement?->window_end;

        if (! $windowEnd) {
            return [];
        }

        $windowEnd = Carbon::parse($windowEnd);

        if ($excludeDate) {
            $windowEnd = $this->alignTimeOfDayTo($windowEnd, $completedAt);
        }

        // diffInMinutes() is signed relative to its argument, so direction is
        // taken from greaterThan() and magnitude from an explicit absolute diff.
        $isLate = $completedAt->greaterThan($windowEnd);

        return [
            'is_on_time' => ! $isLate,
            'delay_minutes' => $isLate ? (int) $completedAt->diffInMinutes($windowEnd, true) : 0,
        ];
    }

    /**
     * Re-date $reference onto $anchor's calendar date (keeping $reference's
     * time-of-day), picking whichever adjacent day keeps it within 12 hours of
     * $anchor. Lets two times be compared on time-of-day alone when their
     * underlying dates are not expected to match.
     */
    private function alignTimeOfDayTo(Carbon $reference, Carbon $anchor): Carbon
    {
        $aligned = $anchor->copy()->setTime($reference->hour, $reference->minute, $reference->second);

        $diffSeconds = $aligned->getTimestamp() - $anchor->getTimestamp();
        if ($diffSeconds > 12 * 3600) {
            $aligned->subDay();
        } elseif ($diffSeconds < -12 * 3600) {
            $aligned->addDay();
        }

        return $aligned;
    }
}
