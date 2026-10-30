<?php

namespace App\Services;

use App\Models\AuditLog;
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

    /** @param  ?int  $eventId  null = every event; 0 = none */
    public function recent(?int $eventId = null, int $limit = 25): array
    {
        $alerts = array_merge(
            $this->fromCheckpoints($eventId),
            $this->fromIssues($eventId),
            $this->fromDelayedMovements($eventId),
            $this->fromAgencyCrewChanges($eventId),
            $this->fromFlightJobChanges($eventId),
            $this->fromDeletions($eventId),
        );

        usort($alerts, fn ($a, $b) => ($b['at'] ?? '') <=> ($a['at'] ?? ''));

        return array_slice($alerts, 0, $limit);
    }

    private function fromCheckpoints(?int $eventId): array
    {
        $checkpoints = JobCheckpoint::with('job.team')
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
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
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
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
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
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

    private function fromAgencyCrewChanges(?int $eventId): array
    {
        return AuditLog::where('action', JobLifecycleService::CREW_ASSIGNED)
            ->where('user_role', 'agency')
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
            ->latest()
            ->limit(self::SCAN_LIMIT)
            ->get()
            ->map(fn (AuditLog $log) => $this->alert(
                'crew-'.$log->id,
                'neutral',
                'Agency crew change: '.$log->target,
                $log->meta.' — '.$log->user_name,
                $log->created_at,
            ))
            ->all();
    }

    /** Jobs a flight change re-timed, or that had started and need a look (FlightJobRetimer's audit entries). */
    private function fromFlightJobChanges(?int $eventId): array
    {
        return AuditLog::whereIn('action', [FlightJobRetimer::RETIMED, FlightJobRetimer::REVIEW])
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
            ->latest()
            ->limit(self::SCAN_LIMIT)
            ->get()
            ->map(function (AuditLog $log) {
                $meta = json_decode((string) $log->meta, true) ?: [];
                $retimed = $log->action === FlightJobRetimer::RETIMED;
                // Older entries stored each clash as plain text.
                $clashes = array_map(fn ($c) => is_array($c) ? $c + ['type' => null] : ['type' => null, 'text' => (string) $c], $meta['clashes'] ?? []);
                [$jobRef, $team] = array_pad(explode(' · ', (string) $log->target, 2), 2, null);
                $flight = $this->flightChange((string) ($meta['flight'] ?? ''));
                $start = $this->span((string) ($meta['start'] ?? ''));

                $summary = array_filter([
                    $flight['number'] ? "{$flight['number']} ".($flight['shift'] ?? 'changed') : null,
                    $retimed ? ($start['to'] ? 'job now starts '.$start['to'] : 'job re-timed') : 'job kept its times',
                    $clashes ? count($clashes).' '.(count($clashes) === 1 ? 'clash' : 'clashes').' to check' : null,
                ]);

                return $this->alert(
                    'flightjob-'.$log->id,
                    $retimed ? ($clashes ? 'danger' : 'warn') : 'danger',
                    ($retimed ? 'Job re-timed: ' : 'Started job needs review: ').$log->target,
                    ucfirst(implode(' · ', $summary)).'.',
                    $log->created_at,
                    [
                        'kind' => $retimed ? 'retimed' : 'review',
                        'job_ref' => $jobRef,
                        'team' => $team,
                        'url' => $jobRef ? '/job/'.$jobRef : null,
                        'flight' => $flight,
                        'start' => $start,
                        'clashes' => $clashes,
                    ],
                );
            })
            ->all();
    }

    /** "QR893 Wed 14 Oct 05:40 → Wed 14 Oct 09:40" (FlightJobRetimer's label) as number, times and the shift. */
    private function flightChange(string $label): array
    {
        $when = '(?:\w{3} \d{1,2} \w{3} \d{2}:\d{2}|—)';
        if (! preg_match("/^(.*?)\\s*({$when}) → ({$when})$/u", $label, $m)) {
            return ['number' => $label ?: null, 'from' => null, 'to' => null, 'shift' => null];
        }
        $span = $this->span("{$m[2]} → {$m[3]}");

        return ['number' => $m[1] ?: null] + $span + ['shift' => $this->shift($span['from'], $span['to'])];
    }

    /** "Wed 14 Oct 04:40 → Wed 14 Oct 08:40" as from / to ("—" = none). */
    private function span(string $text): array
    {
        [$from, $to] = array_pad(array_map('trim', explode('→', $text, 2)), 2, null);

        return ['from' => $from && $from !== '—' ? $from : null, 'to' => $to && $to !== '—' ? $to : null];
    }

    /** "4h later", "35 min earlier"; null when either time is missing. */
    private function shift(?string $from, ?string $to): ?string
    {
        // The weekday is dropped: PHP would move the date to match it.
        $parse = fn (?string $t) => $t ? Carbon::createFromFormat('j M H:i', preg_replace('/^\w{3} /', '', $t)) : null;
        try {
            $a = $parse($from);
            $b = $parse($to);
        } catch (\Throwable) {
            return null;
        }
        if (! $a || ! $b) {
            return null;
        }
        $minutes = (int) round($a->diffInMinutes($b, false));
        if ($minutes === 0) {
            return null;
        }
        $m = abs($minutes);
        $size = $m >= 60 ? intdiv($m, 60).'h'.($m % 60 ? sprintf(' %02dm', $m % 60) : '') : "{$m} min";

        return $size.($minutes < 0 ? ' earlier' : ' later');
    }

    /** Delete requests waiting for a second admin, and mass-delete brake trips; admins only. */
    private function fromDeletions(?int $eventId): array
    {
        if (! auth()->user()?->can('plans.manage')) {
            return [];
        }

        return \App\Models\Deletion::pending()
            ->when($eventId !== null, fn ($q) => $q->where(fn ($w) => $w->where('event_id', $eventId)->orWhereNull('event_id')))
            ->latest()
            ->limit(self::SCAN_LIMIT)
            ->get()
            ->map(fn (\App\Models\Deletion $d) => $this->alert(
                'deletion-'.$d->id,
                $d->trigger === 'brake' ? 'danger' : 'warn',
                ($d->trigger === 'brake' ? 'Mass-delete brake: ' : 'Delete waiting for approval: ').\App\Models\Deletion::label($d->kind, $d->item_count),
                "Asked by {$d->requester_name}. Approve or reject it in Setups → Recycle bin.",
                $d->created_at,
            ))
            ->all();
    }

    /** @param  array<string, mixed>|null  $detail  structured fields a page may render instead of the body text */
    private function alert(string $id, string $tone, string $title, string $body, ?Carbon $at, ?array $detail = null): array
    {
        return [
            'id' => $id,
            'tone' => $tone,
            'title' => $title,
            'body' => $body,
            'at' => \App\Support\WallClock::iso($at),
            't' => $at?->diffForHumans() ?? '—',
        ] + ($detail ? ['detail' => $detail] : []);
    }
}
