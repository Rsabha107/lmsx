<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Driver;
use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\User;
use App\Models\Vehicle;
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

    /** Audit action written by assignMovementCrew; the notification feed reads it back. */
    public const CREW_ASSIGNED = 'Crew assigned';

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

            if ($to === 'cancelled') {
                $attrs['cancelled_from'] = $from;
            }

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
     * Undo a cancellation: put the job back in the status it had when it was cancelled.
     *
     * @throws RuntimeException when the job is not cancelled
     */
    public function reinstate(JobOperation $job, ?User $actor = null): JobOperation
    {
        return DB::transaction(function () use ($job) {
            $job->newQuery()->whereKey($job->getKey())->lockForUpdate()->firstOrFail();
            $job->refresh();

            $to = $job->reinstateStatus();
            if ($to === null) {
                throw new RuntimeException("Job {$job->job_id} is not cancelled.");
            }

            $job->update(['status' => $to, 'cancelled_from' => null]);

            AuditLog::record(
                action: 'Job reinstated',
                target: $job->job_id.($job->team ? ' · '.$job->team->team_name : ''),
                meta: "cancelled → {$to}",
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

                $time = $this->resolveTimeEvidence($checkpoint, $data);
                $completedAt = $time['completed_at'];

                $attrs = [
                    'state' => 'done',
                    'completed_by' => $actor->id,
                    'completion_method' => $method,
                    'completed_at' => $completedAt,
                    'actual_duration_seconds' => $this->resolveDuration($checkpoint, $completedAt),
                ] + $time['attrs'];

                if (array_key_exists('notes', $data)) {
                    $attrs['notes'] = $data['notes'];
                }

                if (! empty($data['client_op_id'])) {
                    $attrs['client_op_id'] = $data['client_op_id'];
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
                    meta: $method.($attrs['is_on_time'] ?? true ? '' : ' · late').' · time: '.$attrs['time_source'],
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

        if (! in_array($state, ['done', 'skipped', 'missed', 'pending'], true)) {
            throw new RuntimeException("Cannot override a checkpoint to '{$state}'.");
        }

        if ($state === 'pending') {
            return $this->resetCheckpoint($checkpoint, $data);
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
                        'received_at' => now(),
                        'time_source' => 'override',
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
                    $this->reassignCrew($checkpoint->job, $data['driver_id'] ?? null, $data['supervisor_id'] ?? null, $data['reason'], $data['vehicle_id'] ?? null);
                }

                return $checkpoint->fresh();
            });
        } catch (Throwable $e) {
            $this->discardEvidence($stored);

            throw $e;
        }
    }

    /**
     * Put a done or skipped checkpoint back to not processed, clearing what the
     * completion or skip recorded (including its photo and signature). The reason
     * and who did it stay in the audit trail only.
     *
     * @param  array{reason: string, driver_id?: ?int, supervisor_id?: ?int, vehicle_id?: ?int}  $data
     *
     * @throws RuntimeException when the checkpoint is not resolved or the job is finished
     */
    private function resetCheckpoint(JobCheckpoint $checkpoint, array $data): JobCheckpoint
    {
        $files = [];

        $result = DB::transaction(function () use (&$files, $checkpoint, $data) {
            $this->lockCheckpoint($checkpoint);
            $job = $checkpoint->job;

            if (! in_array($checkpoint->state, self::RESOLVED_STATES, true)) {
                throw new RuntimeException('Checkpoint has not been processed, so there is nothing to reset.');
            }

            if ($job && in_array($job->status, ['completed', 'cancelled'], true)) {
                throw new RuntimeException("Job {$job->job_id} is {$job->status}; its checkpoints can no longer be reset.");
            }

            $was = $checkpoint->state;
            $files = array_filter([$checkpoint->photo_path, $checkpoint->signature_path]);

            $checkpoint->update([
                'state' => 'pending',
                'completed_at' => null, 'completed_by' => null, 'completion_method' => null,
                'actual_duration_seconds' => null, 'is_on_time' => null, 'delay_minutes' => null,
                'skip_reason' => null, 'skipped_by' => null, 'skipped_at' => null, 'exception_type' => null,
                'photo_path' => null, 'signature_path' => null, 'photo_data' => null, 'signature_data' => null, 'notes' => null,
                'planned_bags' => null, 'bags_loaded' => 0, 'food_bags' => null, 'oversized_pieces' => 0,
                'gps_latitude' => null, 'gps_longitude' => null,
                'event_at' => null, 'received_at' => null, 'clock_skew_seconds' => null, 'time_source' => null,
                'was_overridden' => false, 'override_reason' => null, 'override_notes' => null,
                'overridden_by' => null, 'overridden_at' => null, 'override_actual_time' => null,
            ]);

            if ($job) {
                $this->recountProgress($job);
            }

            AuditLog::record(
                action: 'Checkpoint reset',
                target: ($job?->job_id ?? 'JOB').' · '.$checkpoint->name,
                meta: $was.' → pending · '.$data['reason'],
                subject: $job,
                eventId: $job?->event_id,
            );

            if ($job) {
                $this->reassignCrew($job, $data['driver_id'] ?? null, $data['supervisor_id'] ?? null, $data['reason'], $data['vehicle_id'] ?? null);
            }

            return $checkpoint->fresh();
        });

        // Only once the reset is committed, so a rollback keeps the files.
        $this->discardEvidence($files);

        return $result;
    }

    /**
     * Change a job's crew without touching any checkpoint.
     *
     * @return bool whether anything actually changed
     */
    public function changeCrew(JobOperation $job, ?int $driverId, ?int $supervisorId, ?string $reason, ?int $vehicleId = null): bool
    {
        return DB::transaction(fn () => $this->reassignCrew($job, $driverId, $supervisorId, $reason, $vehicleId));
    }

    /**
     * Correct the flight behind a job. Only the keys present in $changes are touched.
     *
     * @param  array{flight_number?: ?string, scheduled_at?: ?string, terminal?: ?string, gate?: ?string}  $changes
     * @return bool whether anything actually changed
     */
    public function changeFlight(JobOperation $job, array $changes, ?string $reason): bool
    {
        $flight = $job->movement?->flight;

        if (! $flight || $changes === []) {
            return false;
        }

        return DB::transaction(function () use ($job, $flight, $changes, $reason) {
            $labels = ['flight_number' => 'Flight', 'scheduled_at' => 'Scheduled', 'terminal' => 'Terminal', 'gate' => 'Gate'];
            $show = fn ($v) => $v instanceof Carbon ? $v->format('D j M H:i') : ($v === null || $v === '' ? '—' : $v);
            $log = [];

            foreach ($changes as $field => $value) {
                $value = $field === 'scheduled_at' && $value ? Carbon::parse($value) : ($value === '' ? null : $value);
                $before = $flight->{$field};
                $same = $before instanceof Carbon && $value instanceof Carbon ? $before->equalTo($value) : $before == $value;
                if ($same) {
                    continue;
                }
                $log[] = "{$labels[$field]} {$show($before)} → {$show($value)}";
                $flight->{$field} = $value;
            }

            if ($log === []) {
                return false;
            }

            $timeChanged = $flight->isDirty('scheduled_at');

            $flight->save();

            // The movement keeps its own copy of the number for Planning and search.
            if (array_key_exists('flight_number', $changes)) {
                $job->movement->update(['flight_number' => $flight->flight_number]);
            }

            if ($timeChanged) {
                $moved = app(JobGenerationService::class)->rescheduleJob($job);
                $log[] = sprintf('%d checkpoint%s and the movement window re-timed from settings', $moved, $moved === 1 ? '' : 's');
            }

            AuditLog::record(
                action: 'Job flight changed',
                target: $job->job_id ?? 'JOB',
                meta: implode('; ', $log).' · '.($reason ?: 'no reason given'),
                subject: $job,
                eventId: $job->event_id,
            );

            return true;
        });
    }

    /**
     * Set a movement's vehicle, driver and supervisor, mirroring them onto its
     * job if one exists. Unlike changeCrew, a null clears that role.
     * $units, when given, replaces the extra vehicle + driver pairs; null leaves them alone.
     * $extraSupervisorIds works the same way for the supervisors beyond the lead.
     *
     * @param  array<int, array{vehicle_id?: ?int, driver_id?: ?int}>|null  $units
     * @param  array<int, int>|null  $extraSupervisorIds
     * @return bool whether anything actually changed
     */
    public function assignMovementCrew(Movement $movement, ?int $vehicleId, ?int $driverId, ?int $supervisorId, ?array $units = null, ?array $extraSupervisorIds = null): bool
    {
        return DB::transaction(function () use ($movement, $vehicleId, $driverId, $supervisorId, $units, $extraSupervisorIds) {
            $movement->loadMissing(['vehicle', 'driver', 'fieldSupervisor', 'job', 'units.vehicle', 'units.driver', 'extraSupervisors']);

            $vehicleName = fn (?Vehicle $v) => $v ? ($v->code ?? $v->plate_number ?? "#{$v->id}") : 'Unassigned';
            $changes = [];

            if ((int) $movement->vehicle_id !== (int) $vehicleId) {
                $changes[] = 'Vehicle '.$vehicleName($movement->vehicle).' → '.$vehicleName($vehicleId ? Vehicle::find($vehicleId) : null);
            }

            if ((int) $movement->driver_id !== (int) $driverId) {
                $changes[] = 'Driver '.($movement->driver?->name ?? 'Unassigned').' → '
                    .($driverId ? (Driver::find($driverId)?->name ?? "#{$driverId}") : 'Unassigned');
            }

            if ((int) $movement->field_supervisor_id !== (int) $supervisorId) {
                $changes[] = 'Supervisor '.($movement->fieldSupervisor?->name ?? 'Unassigned').' → '
                    .($supervisorId ? (User::find($supervisorId)?->name ?? "#{$supervisorId}") : 'Unassigned');
            }

            $newUnits = $units === null ? null : $this->normaliseUnits($units);
            $oldUnits = $movement->units->map(fn ($u) => ['vehicle_id' => $u->vehicle_id, 'driver_id' => $u->driver_id])->all();
            $unitsChanged = $newUnits !== null && $newUnits !== $oldUnits;
            if ($unitsChanged) {
                $describe = fn (array $list) => $list === [] ? 'none' : implode(', ', array_map(
                    fn ($u) => $vehicleName($u['vehicle_id'] ? Vehicle::find($u['vehicle_id']) : null)
                        .' / '.($u['driver_id'] ? (Driver::find($u['driver_id'])?->name ?? "#{$u['driver_id']}") : 'Unassigned'),
                    $list,
                ));
                $changes[] = 'Extra units '.$describe($oldUnits).' → '.$describe($newUnits);
            }

            // The lead can't also be listed as an extra.
            $newSupervisors = $extraSupervisorIds === null ? null : collect($extraSupervisorIds)
                ->map(fn ($id) => (int) $id)->filter()->reject(fn ($id) => $id === (int) $supervisorId)->unique()->sort()->values()->all();
            $oldSupervisors = $movement->extraSupervisors->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            $supervisorsChanged = $newSupervisors !== null && $newSupervisors !== $oldSupervisors;
            if ($supervisorsChanged) {
                $names = fn (array $ids) => $ids === [] ? 'none' : User::whereIn('id', $ids)->orderBy('name')->pluck('name')->implode(', ');
                $changes[] = 'Extra supervisors '.$names($oldSupervisors).' → '.$names($newSupervisors);
            }

            if ($changes === []) {
                return false;
            }

            $movement->update([
                'vehicle_id' => $vehicleId,
                'driver_id' => $driverId,
                'field_supervisor_id' => $supervisorId,
            ]);

            if ($unitsChanged) {
                $movement->units()->delete();
                foreach ($newUnits as $position => $unit) {
                    $movement->units()->create($unit + ['position' => $position]);
                }
                $movement->unsetRelation('units');
            }

            if ($supervisorsChanged) {
                $movement->extraSupervisors()->sync($newSupervisors);
                $movement->unsetRelation('extraSupervisors');
            }

            $movement->job?->update([
                'vehicle_id' => $vehicleId,
                'driver_id' => $driverId,
                'supervisor_id' => $supervisorId,
            ]);

            AuditLog::record(
                action: self::CREW_ASSIGNED,
                target: $movement->code.($movement->job ? ' · '.$movement->job->job_id : ''),
                meta: implode('; ', $changes),
                subject: $movement,
                eventId: $movement->event_id,
            );

            return true;
        });
    }

    /**
     * Drop empty rows and keep only the two crew keys, so comparisons are like for like.
     *
     * @return array<int, array{vehicle_id: ?int, driver_id: ?int}>
     */
    private function normaliseUnits(array $units): array
    {
        return array_values(array_filter(array_map(fn ($u) => [
            'vehicle_id' => isset($u['vehicle_id']) ? (int) $u['vehicle_id'] : null,
            'driver_id' => isset($u['driver_id']) ? (int) $u['driver_id'] : null,
        ], $units), fn ($u) => $u['vehicle_id'] || $u['driver_id']));
    }

    /**
     * Swap the driver and/or supervisor on a job, mirroring the change onto its
     * movement so Planning shows the same crew. A null id leaves that role as is.
     */
    private function reassignCrew(JobOperation $job, ?int $driverId, ?int $supervisorId, ?string $reason, ?int $vehicleId = null): bool
    {
        $changes = [];
        $vehicleName = fn (?Vehicle $v) => $v ? ($v->code ?? $v->plate_number ?? "#{$v->id}") : 'Unassigned';

        if ($vehicleId !== null && $vehicleId !== $job->vehicle_id) {
            $from = $vehicleName($job->vehicle);
            $job->vehicle_id = $vehicleId;
            $changes[] = "Vehicle {$from} → " . $vehicleName(Vehicle::find($vehicleId));
        }

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
            'vehicle_id' => $job->vehicle_id,
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
     * Permanently delete jobs with their checkpoints and issues (FK cascade),
     * freeing their movements so jobs can be generated for them again.
     *
     * @param  iterable<JobOperation>  $jobs
     */
    public function deleteJobs(iterable $jobs): int
    {
        $evidence = [];

        $count = DB::transaction(function () use ($jobs, &$evidence) {
            $count = 0;

            foreach ($jobs as $job) {
                foreach ($job->checkpoints()->get(['photo_path', 'signature_path']) as $checkpoint) {
                    $evidence[] = $checkpoint->photo_path;
                    $evidence[] = $checkpoint->signature_path;
                }

                Movement::withTrashed()
                    ->where('job_id', $job->job_id)
                    ->update(['job_id' => null, 'job_generated_at' => null]);

                AuditLog::record(
                    action: 'Job deleted',
                    target: $job->job_id.($job->team ? ' · '.$job->team->team_name : ''),
                    meta: "Status was {$job->status}",
                    subject: $job,
                    eventId: $job->event_id,
                );

                $job->delete();
                $count++;
            }

            return $count;
        });

        // Only once the rows are gone, so a rolled-back delete keeps its files.
        $this->discardEvidence(array_filter($evidence));

        return $count;
    }

    /**
     * Recount progress and move the job along if the checkpoints imply it.
     */
    public function syncJobProgress(?JobOperation $job, bool $autoComplete = true): void
    {
        if (! $job) {
            return;
        }

        $this->recountProgress($job);
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

    /** Counts only; status moves go through transitionStatus(). */
    private function recountProgress(JobOperation $job): void
    {
        $completed = $job->checkpoints()->where('state', 'done')->count();
        $total = $job->checkpoints()->count();

        $job->update([
            'checkpoints_completed' => $completed,
            'checkpoints_total' => $total,
            'progress_percentage' => $total > 0 ? ($completed / $total) * 100 : 0,
        ]);
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
     * Which time a completion counts as, and the evidence behind it.
     *
     * The app stores venue wall-clock times with no timezone, so a device time (event_at, with the
     * device's own clock at send time in client_sent_at) is shifted by the measured clock skew and
     * then kept as the wall-clock reading in the device's offset. A time that is wildly off, in the
     * future or older than a week is rejected in favour of the server's receipt time; the raw
     * claim is still kept.
     *
     * @param  array<string, mixed>  $data
     * @return array{completed_at: Carbon, attrs: array<string, mixed>}
     */
    private function resolveTimeEvidence(JobCheckpoint $checkpoint, array $data): array
    {
        $received = now();

        if (empty($data['event_at'])) {
            return [
                'completed_at' => $this->resolveCompletedAt($checkpoint, $data['actual_time'] ?? null, $data['exclude_date'] ?? false),
                'attrs' => [
                    'received_at' => $received,
                    'time_source' => ! empty($data['actual_time']) ? 'manual' : 'server',
                ],
            ];
        }

        $claimed = Carbon::parse($data['event_at']);
        $zone = $claimed->getTimezone();
        $wall = fn (Carbon $instant): Carbon => Carbon::parse($instant->copy()->setTimezone($zone)->format('Y-m-d H:i:s'), config('app.timezone'));

        $skew = ! empty($data['client_sent_at'])
            ? (int) round(Carbon::parse($data['client_sent_at'])->diffInSeconds($received, false))
            : null;

        $corrected = $skew === null ? $claimed->copy() : $claimed->copy()->addSeconds($skew);

        $plausible = ($skew === null || abs($skew) <= 86400)
            && $corrected->lessThanOrEqualTo($received->copy()->addMinutes(2))
            && $corrected->greaterThanOrEqualTo($received->copy()->subDays(7));

        $used = $plausible ? ($corrected->greaterThan($received) ? $received : $corrected) : $received;

        return [
            'completed_at' => $wall($used),
            'attrs' => [
                'event_at' => $wall($claimed),
                'received_at' => $wall($received),
                'clock_skew_seconds' => $skew,
                'time_source' => $plausible ? 'device' : 'server',
            ],
        ];
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

        // Time of day only: the nearest day to the plan, as the override modal previews it.
        if ($excludeDate && $checkpoint->scheduled_at) {
            return $this->alignTimeOfDayTo(Carbon::today()->setTime($hours, $minutes), $checkpoint->scheduled_at);
        }

        $completedAt = Carbon::today()->setTime($hours, $minutes);

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
