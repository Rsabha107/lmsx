<?php

namespace App\Services;

use App\Models\JobCheckpoint;
use App\Models\JobIssue;
use App\Models\Movement;
use Illuminate\Support\Carbon;

/**
 * Derives the notification feed from real operational rows.
 *
 * There is no notifications table: alerts are computed from checkpoint
 * outcomes, open job issues, and delayed movements.
 */
class NotificationFeedService
{
    /** How many rows of each source to scan before merging and trimming. */
    private const SCAN_LIMIT = 60;

    public function recent(?int $eventId = null, int $limit = 25): array
    {
        $alerts = array_merge(
            $this->fromCheckpoints($eventId),
            $this->fromIssues($eventId),
            $this->fromDelayedMovements($eventId),
        );

        usort($alerts, fn ($a, $b) => ($b['at'] ?? '') <=> ($a['at'] ?? ''));

        return array_slice($alerts, 0, $limit);
    }

    private function fromCheckpoints(?int $eventId): array
    {
        $checkpoints = JobCheckpoint::with('job.team')
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->where(function ($q) {
                $q->where('was_overridden', true)
                    ->orWhere('state', 'skipped')
                    ->orWhere(fn ($inner) => $inner->where('state', 'done')->where('is_on_time', false));
            })
            ->orderByDesc('updated_at')
            ->limit(self::SCAN_LIMIT)
            ->get();

        $alerts = [];

        foreach ($checkpoints as $cp) {
            $team = $cp->job?->team?->team_name ?? 'Unknown team';
            $jobRef = $cp->job?->job_id ?? 'JOB-'.$cp->job_id;
            $delay = (int) ($cp->delay_minutes ?? 0);

            if ($cp->state === 'done' && $cp->is_on_time === false) {
                $alerts[] = $this->alert(
                    'late-'.$cp->id,
                    $delay > 15 ? 'danger' : 'warn',
                    'Late: '.$cp->name,
                    "$jobRef · $team · {$delay} min past the movement window.",
                    $cp->completed_at ?? $cp->updated_at,
                );
            }

            if ($cp->was_overridden) {
                $alerts[] = $this->alert(
                    'override-'.$cp->id,
                    'warn',
                    'Overridden: '.$cp->name,
                    "$jobRef · ".($cp->override_reason ?: 'No reason recorded.'),
                    $cp->overridden_at ?? $cp->updated_at,
                );
            }

            if ($cp->state === 'skipped') {
                $alerts[] = $this->alert(
                    'skip-'.$cp->id,
                    'neutral',
                    'Skipped: '.$cp->name,
                    "$jobRef · ".($cp->skip_reason ?: 'No reason recorded.'),
                    $cp->skipped_at ?? $cp->updated_at,
                );
            }
        }

        return $alerts;
    }

    private function fromIssues(?int $eventId): array
    {
        return JobIssue::with(['job', 'reporter'])
            ->open()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->latest()
            ->limit(self::SCAN_LIMIT)
            ->get()
            ->map(fn (JobIssue $issue) => $this->alert(
                'issue-'.$issue->id,
                $issue->severity ?: 'warn',
                'Issue: '.$issue->label(),
                ($issue->job?->job_id ?? 'JOB').' · '
                    .($issue->notes ?: 'No detail given.')
                    .($issue->reporter ? ' — '.$issue->reporter->name : ''),
                $issue->created_at,
            ))
            ->all();
    }

    private function fromDelayedMovements(?int $eventId): array
    {
        return Movement::with('team')
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->where('delay_minutes', '>', 0)
            ->orderByDesc('updated_at')
            ->limit(self::SCAN_LIMIT)
            ->get()
            ->map(function (Movement $movement) {
                $delay = (int) $movement->delay_minutes;
                $team = $movement->team?->team_name ?? 'Unknown team';
                $window = $movement->window_start?->format('H:i') ?? '--:--';

                return $this->alert(
                    'delay-'.$movement->id,
                    $delay > 15 ? 'danger' : 'warn',
                    "Movement delayed by {$delay} min",
                    "$team · ".($movement->from_location ?? '—').' → '
                        .($movement->to_location ?? '—')." · window {$window}.",
                    $movement->updated_at,
                );
            })
            ->all();
    }

    private function alert(string $id, string $tone, string $title, string $body, ?Carbon $at): array
    {
        return [
            'id' => $id,
            'tone' => $tone,
            'title' => $title,
            'body' => $body,
            'at' => $at?->toIso8601String(),
            't' => $at?->diffForHumans() ?? '—',
        ];
    }
}
