<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\TeamFlight;
use Carbon\Carbon;

/**
 * A flight's scheduled time moved under a movement that already has a job (FlightMovementSync::sync,
 * so the Event Teams edits, the team import and the OAG refresh). A job not started yet is re-timed
 * the way generation timed it (window + outstanding checkpoints); a started one keeps its times and is
 * flagged. Each case is audited (the Dashboard / Notifications feed reads it) and handed to
 * FlightChangeDigest, which pushes the supervisors and emails planners once per save / refresh / sync.
 * Crew clashes at the new time are listed.
 */
class FlightJobRetimer
{
    public const RETIMED = 'Job re-timed (flight change)';
    public const REVIEW = 'Job needs review (flight change)';

    private const CLASHES = 3;

    public function __construct(
        private JobGenerationService $jobs,
        private JobPushNotifier $push,
        private ConflictDetectionService $conflicts,
        private FlightChangeDigest $digest,
    ) {}

    /** Not started: still pending and no checkpoint done or skipped. */
    public static function notStarted(JobOperation $job): bool
    {
        return ! in_array($job->status, ['in-progress', 'completed', 'cancelled'], true)
            && ! $job->checkpoints()->whereIn('state', ['done', 'skipped'])->exists();
    }

    /**
     * @param  array{flight_number: ?string, scheduled_at: ?Carbon, party_size_total: ?int}  $before
     * @return array{status: string, notes: array<int, string>, job_ref?: string}|null  null for a finished job (nothing to do)
     */
    public function handle(Movement $movement, TeamFlight $flight, array $before): ?array
    {
        $job = $movement->job;
        if (! $job) {
            return ['status' => 'needs_review', 'notes' => ["Job {$movement->job_id} was already issued - update it by hand."]];
        }
        if (in_array($job->status, ['completed', 'cancelled'], true)) {
            return null;
        }

        $label = trim(($flight->flight_number ?? '').' '.$this->when($before['scheduled_at']).' → '.$this->when($flight->scheduled_at));
        $shift = $before['scheduled_at'] && $flight->scheduled_at
            ? (int) round($before['scheduled_at']->diffInMinutes($flight->scheduled_at, false))
            : null;
        $startBefore = $this->push->startsAt($job);

        if (! self::notStarted($job)) {
            $this->record(self::REVIEW, $job, ['flight' => $label, 'start' => $this->when($startBefore)]);
            $this->tell($job, 'review', $label, $shift, $startBefore, null, []);

            // Fields for the flight-change list (FlightChangesList); notes stay for anything that only reads text.
            return ['status' => 'needs_review', 'job_ref' => $job->job_id, 'job_started' => true,
                'start_before' => $this->when($startBefore), 'notified' => true, 'notes' => []];
        }

        $moved = $this->jobs->rescheduleJob($job, notify: false);
        $job->refresh();
        $startAfter = $this->push->startsAt($job);
        $clashes = $this->clashes($movement->refresh());

        $start = $this->when($startBefore).' → '.$this->when($startAfter);
        $this->record(self::RETIMED, $job, array_filter([
            'flight' => $label,
            'start' => $start,
            'checkpoints' => $moved,
            'clashes' => $clashes ?: null,
        ]));
        $this->tell($job, 'retimed', $label, $shift, $startBefore, $startAfter, $clashes);

        return [
            'status' => 'job_retimed',
            'job_ref' => $job->job_id,
            'start_before' => $this->when($startBefore),
            'start_after' => $this->when($startAfter),
            'checkpoints_moved' => $moved,
            'notified' => true,
            'clashes' => count($clashes),
            'clash_list' => $clashes,
            'notes' => [],
        ];
    }

    /**
     * Unaccepted high / medium conflicts involving the movement at its new time.
     *
     * @return array<int, array{type: string, text: string}>
     */
    private function clashes(Movement $movement): array
    {
        return collect($this->conflicts->forEvent($movement->event_id))
            ->filter(fn ($c) => ! $c['accepted'] && in_array($c['sev'], ['high', 'medium'], true)
                && in_array($movement->id, $c['movement_ids'], true))
            ->take(self::CLASHES)
            ->map(fn ($c) => ['type' => (string) $c['type'], 'text' => (string) $c['text']])
            ->values()
            ->all();
    }

    private function record(string $action, JobOperation $job, array $summary): void
    {
        AuditLog::change($action, $job->job_id.(($team = $this->team($job)) ? " · {$team}" : ''), $summary, $job, $job->event_id);
    }

    private function tell(JobOperation $job, string $kind, string $flight, ?int $shift, ?Carbon $startBefore, ?Carbon $startAfter, array $clashes): void
    {
        $this->digest->add([
            'event_id' => $job->event_id,
            'job_id' => $job->id,
            'job_ref' => $job->job_id,
            'team' => $this->team($job),
            'kind' => $kind,
            'flight' => $flight,
            'shift' => $shift,
            'start_before' => $startBefore ? $this->when($startBefore) : null,
            'start_after' => $startAfter ? $this->when($startAfter) : null,
            'clashes' => $clashes,
            'supervisor_ids' => $this->push->supervisorIds($job->movement_id),
        ]);
    }

    private function team(JobOperation $job): ?string
    {
        return $job->team?->team_name ?? $job->movement?->team?->team_name;
    }

    private function when(?Carbon $at): string
    {
        return $at ? $at->format('D j M H:i') : '—';
    }
}
