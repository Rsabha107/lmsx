<?php

namespace App\Services;

use App\Jobs\SendPushNotification;
use App\Models\JobOperation;
use App\Models\Movement;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Push notifications to the field supervisors of a job: added / removed,
 * new jobs, re-timed jobs and the "starts soon" reminders.
 */
class JobPushNotifier
{
    /** Nested crew changes (e.g. updateMovement → assignMovementCrew) report once, from the outermost. */
    private static int $tracking = 0;

    /**
     * Runs a crew change and tells supervisors who were added to or removed from the job.
     *
     * @template T
     *
     * @param  Closure(): T  $change
     * @return T
     */
    public function trackCrew(Movement $movement, Closure $change): mixed
    {
        if (self::$tracking > 0) {
            return $change();
        }

        $before = $this->supervisorIds($movement->id);
        self::$tracking++;
        try {
            $result = $change();
        } finally {
            self::$tracking--;
        }

        $fresh = Movement::with(['job.team', 'team'])->find($movement->id);
        $job = $fresh?->job;
        if (! $job || ! $this->isActive($job)) {
            return $result;
        }

        $after = $this->supervisorIds($movement->id);
        $label = $this->label($job);
        $starts = $this->startsAt($job);

        $this->send(array_diff($after, $before), 'job_assigned', 'You have a new job', $label.($starts ? ' · starts '.$starts->format('D j M H:i') : ''), $job);
        $this->send(array_diff($before, $after), 'job_removed', 'You were taken off a job', $label, $job);

        return $result;
    }

    /**
     * One push per supervisor for a batch of freshly generated jobs.
     *
     * @param  array<int, JobOperation>  $jobs
     */
    public function jobsCreated(array $jobs): void
    {
        $byUser = [];
        foreach ($jobs as $job) {
            foreach ($this->supervisorIds($job->movement_id) as $userId) {
                $byUser[$userId][] = $job;
            }
        }

        foreach ($byUser as $userId => $list) {
            if (count($list) === 1) {
                $job = $list[0];
                $starts = $this->startsAt($job);
                $this->send([$userId], 'job_assigned', 'You have a new job', $this->label($job).($starts ? ' · starts '.$starts->format('D j M H:i') : ''), $job);
            } else {
                $this->send([$userId], 'jobs_assigned', 'You have '.count($list).' new jobs', 'Open the app to see them.');
            }
        }
    }

    public function retimed(JobOperation $job): void
    {
        if (! $this->isActive($job)) {
            return;
        }

        $starts = $this->startsAt($job);
        $this->send($this->supervisorIds($job->movement_id), 'job_retimed', 'Job times changed',
            $this->label($job).($starts ? ' · now starts '.$starts->format('D j M H:i') : ''), $job);
    }

    /**
     * Flight changes under one supervisor's jobs (FlightChangeDigest): the job's own message, or one merged
     * push when several of their jobs changed in the same save, refresh or sync.
     *
     * @param  array<int, array{job_id: int, job_ref: string, team: ?string, kind: string, flight: string, start_after: ?string}>  $items
     */
    public function flightChangesFor(int $userId, array $items): void
    {
        if (count($items) === 1) {
            $item = $items[0];
            $retimed = $item['kind'] === 'retimed';
            $this->send([$userId], 'job_flight_changed',
                $retimed ? 'Job re-timed: flight changed' : 'Flight changed: check your job',
                $this->itemLabel($item).' · '.$item['flight'].'. '.($retimed ? "Now starts {$item['start_after']}." : 'The job has started and kept its times.'),
                JobOperation::find($item['job_id']));

            return;
        }

        $retimed = count(array_filter($items, fn ($i) => $i['kind'] === 'retimed'));
        $title = count($items).' of your jobs changed with their flights';
        $body = implode('; ', array_map(fn ($i) => $this->itemLabel($i).($i['kind'] === 'retimed' ? " now {$i['start_after']}" : ' needs a check'), array_slice($items, 0, 4)))
            .(count($items) > 4 ? '; and '.(count($items) - 4).' more' : '')
            .($retimed < count($items) ? '.' : '. All re-timed.');
        $this->send([$userId], 'job_flight_changed', $title, $body);
    }

    private function itemLabel(array $item): string
    {
        return $item['job_ref'].($item['team'] ? ' · '.$item['team'] : '');
    }

    /**
     * @param  array<int, int>  $userIds
     */
    public function startingSoon(JobOperation $job, Carbon $startsAt, array $userIds): void
    {
        $minutes = max(1, (int) ceil(now()->diffInSeconds($startsAt, false) / 60));
        $this->send($userIds, 'job_starting', "Job starts in {$minutes} min",
            $this->label($job).' · '.$startsAt->format('H:i'), $job, excludeActor: false);
    }

    /** The job's first scheduled checkpoint, or its movement window when none has a time. */
    public function startsAt(JobOperation $job): ?Carbon
    {
        $first = $job->relationLoaded('checkpoints')
            ? $job->checkpoints->whereNotNull('scheduled_at')->min('scheduled_at')
            : $job->checkpoints()->whereNotNull('scheduled_at')->min('scheduled_at');

        return $first ? Carbon::parse($first) : $job->movement?->window_start;
    }

    /** @return array<int, int> */
    public function supervisorIds(?int $movementId): array
    {
        $movement = $movementId ? Movement::with('extraSupervisors:id')->find($movementId) : null;

        return $movement ? $movement->resourceIds('field_supervisor_id') : [];
    }

    private function isActive(JobOperation $job): bool
    {
        return ! in_array($job->status, ['completed', 'cancelled'], true);
    }

    private function label(JobOperation $job): string
    {
        $team = $job->team?->team_name ?? $job->movement?->team?->team_name;

        return $job->job_id.($team ? ' · '.$team : '');
    }

    /**
     * @param  array<int, int>  $userIds
     */
    private function send(array $userIds, string $type, string $title, string $body, ?JobOperation $job = null, bool $excludeActor = true): void
    {
        // Nobody needs a push about their own change.
        $userIds = array_values(array_diff($userIds, $excludeActor && Auth::id() ? [Auth::id()] : []));
        if ($userIds === []) {
            return;
        }

        SendPushNotification::dispatchAfterResponse($userIds, $type, $title, $body, $job ? [
            'job_id' => $job->id,
            'job_ref' => (string) $job->job_id,
            'event_id' => $job->event_id,
        ] : []);
    }
}
