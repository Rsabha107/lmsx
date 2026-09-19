<?php

namespace App\Services;

use App\Models\Event;
use App\Models\JobCheckpoint;
use App\Models\JobIssue;
use App\Models\Movement;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Builds the daily operations snapshot (KPIs, movements, recipients) from
 * real rows for a single event/day.
 */
class DailySummaryService
{
    public function build(?int $eventId, string $date): array
    {
        $movements = $this->movements($eventId, $date);

        return [
            'event' => $eventId ? Event::find($eventId)?->only(['id', 'name', 'short_name']) : null,
            'date' => $date,
            'kpis' => $this->kpis($eventId, $date, $movements),
            'schedule' => $movements,
            'recipients' => $this->recipients($eventId),
        ];
    }

    /**
     * Today's movements in the same shape the Schedule page uses.
     */
    private function movements(?int $eventId, string $date): array
    {
        return Movement::with(['team', 'flight', 'job.vehicle'])
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->whereDate('window_start', $date)
            ->orderBy('window_start')
            ->get()
            ->map(function (Movement $movement) {
                $job = $movement->job;
                $delay = (int) ($movement->delay_minutes ?? 0);

                $status = 'scheduled';
                if ($delay > 0) {
                    $status = 'delayed';
                } elseif ($job?->status === 'completed') {
                    $status = 'done';
                } elseif ($job?->status === 'in-progress') {
                    $status = 'in-progress';
                }

                return [
                    'id' => $job?->job_id ?? 'MV-'.$movement->id,
                    'code' => $movement->team?->code ?? 'UNK',
                    'team' => $movement->team?->team_name ?? 'Unknown team',
                    'from' => $movement->from_location ?? '—',
                    'to' => $movement->to_location ?? '—',
                    'dep' => $movement->window_start?->format('H:i') ?? '--:--',
                    'arr' => $movement->window_end?->format('H:i') ?? '--:--',
                    'pax' => $movement->passengers ?? $movement->flight?->party_size_total ?? $movement->team?->party_size_total ?? 0,
                    'vehicle' => $job?->vehicle?->code ?? $job?->vehicle?->plate_number ?? 'Unassigned',
                    'status' => $status,
                    'delay' => $delay > 0 ? $delay : null,
                ];
            })
            ->all();
    }

    private function kpis(?int $eventId, string $date, array $movements): array
    {
        $day = Carbon::parse($date);

        $checkpoints = fn () => JobCheckpoint::query()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->whereDate('created_at', '<=', $day->copy()->endOfDay());

        $total = $checkpoints()->count();
        $done = $checkpoints()->where('state', 'done')->count();
        $onTime = $checkpoints()->where('state', 'done')->where('is_on_time', true)->count();

        $delays = collect($movements)->pluck('delay')->filter()->values();
        $avgDelay = $delays->isEmpty() ? 0 : round($delays->avg(), 1);

        $planned = count($movements);
        $completed = collect($movements)->where('status', 'done')->count();

        $openIssues = JobIssue::open()
            ->when($eventId, fn ($q) => $q->where('event_id', $eventId))
            ->count();

        return [
            [
                'id' => 'otar',
                'label' => 'On-time arrival rate',
                'value' => $done > 0 ? round(($onTime / $done) * 100, 1).'%' : '—',
                'delta' => "$onTime of $done completed checkpoints",
                'tone' => $this->tone($done > 0 ? ($onTime / $done) * 100 : 100, 90, 75),
            ],
            [
                'id' => 'avgd',
                'label' => 'Avg. delay',
                'value' => $avgDelay > 0 ? $avgDelay.' min' : '0 min',
                'delta' => $delays->count().' delayed movement(s)',
                'tone' => $avgDelay <= 5 ? 'ok' : ($avgDelay <= 15 ? 'warn' : 'danger'),
            ],
            [
                'id' => 'jobs',
                'label' => 'Jobs completed / planned',
                'value' => "$completed / $planned",
                'delta' => $planned > 0 ? round(($completed / $planned) * 100).'%' : '—',
                'tone' => 'primary',
            ],
            [
                'id' => 'chk',
                'label' => 'Checkpoint compliance',
                'value' => $total > 0 ? round(($done / $total) * 100, 1).'%' : '—',
                'delta' => "$done of $total checkpoints",
                'tone' => $this->tone($total > 0 ? ($done / $total) * 100 : 100, 95, 85),
            ],
            [
                'id' => 'iss',
                'label' => 'Open issues',
                'value' => (string) $openIssues,
                'delta' => $openIssues === 0 ? 'none outstanding' : 'awaiting resolution',
                'tone' => $openIssues === 0 ? 'ok' : ($openIssues < 5 ? 'warn' : 'danger'),
            ],
        ];
    }

    /**
     * Recipients are the users assigned to the event; without an event the
     * snapshot has no audience.
     */
    private function recipients(?int $eventId): array
    {
        if (! $eventId) {
            return [];
        }

        return User::whereHas('events', fn ($q) => $q->where('events.id', $eventId))
            ->whereNotNull('email')
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->toArray();
    }

    private function tone(float $percent, float $okAt, float $warnAt): string
    {
        return $percent >= $okAt ? 'ok' : ($percent >= $warnAt ? 'warn' : 'danger');
    }
}
