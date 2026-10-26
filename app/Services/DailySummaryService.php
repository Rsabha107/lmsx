<?php

namespace App\Services;

use App\Models\Event;
use App\Models\JobCheckpoint;
use App\Models\JobIssue;
use App\Models\JobOperation;
use App\Models\Movement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The Daily Email for one event and day D: what needs attention today and tomorrow, how yesterday
 * went, today at a glance, and D's movements. Sent by hand from the Daily Email page to the
 * event's chosen audience (Setups → Settings → Notifications).
 */
class DailySummaryService
{
    /** Example rows listed under each attention block; the count is always the full number. */
    private const EXAMPLES = 5;

    public function __construct(private ConflictDetectionService $conflicts)
    {
    }

    /** @param  ?int  $eventId  null = every event; 0 = none */
    public function build(?int $eventId, string $date): array
    {
        $day = Carbon::parse($date)->startOfDay();
        $event = $eventId ? Event::find($eventId) : null;
        $attention = $this->attention($eventId, $day);
        $total = array_sum(array_column($attention, 'count'));
        $label = $event?->short_name ?: $event?->name ?: 'NAQLA LMS';

        return [
            'event' => $event?->only(['id', 'name', 'short_name']),
            'date' => $day->toDateString(),
            'subject' => ($total > 0 ? "⚠ {$total} ".($total === 1 ? 'item needs' : 'items need').' attention' : '✓ All set')
                ." · {$label} · ".$day->format('D j M'),
            'attention' => $attention,
            'attentionTotal' => $total,
            'yesterday' => $this->yesterday($eventId, $day->copy()->subDay()),
            // Not 'today': that name is the shared top-bar date (HandleInertiaRequests).
            'glance' => $this->today($eventId, $day),
            'schedule' => $this->schedule($eventId, $day),
            'recipients' => $this->recipients($eventId),
        ];
    }

    /** Live (not cancelled or completed) movements starting on D or D+1. */
    private function upcoming(?int $eventId, Carbon $day): Collection
    {
        return Movement::with(['team:id,code,team_name', 'job:id,movement_id'])
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where('window_start', '>=', $day)
            ->where('window_start', '<', $day->copy()->addDays(2))
            ->orderBy('window_start')
            ->get();
    }

    /** Only blocks with something in them; each links to the screen that fixes it. */
    private function attention(?int $eventId, Carbon $day): array
    {
        $movements = $this->upcoming($eventId, $day);
        $blocks = [];

        $missing = $movements->filter(fn (Movement $m) => ! $m->vehicle_id || ! $m->driver_id || ! $m->field_supervisor_id);
        $blocks[] = $this->block('crew', 'Movements missing crew', $missing->count(),
            $missing->take(self::EXAMPLES)->map(fn (Movement $m) => $this->movementLine($m).' · no '.implode(', ', array_keys(array_filter([
                'vehicle' => ! $m->vehicle_id, 'driver' => ! $m->driver_id, 'supervisor' => ! $m->field_supervisor_id,
            ]))))->values()->all(),
            url('/crew-assignment?date='.$day->toDateString()), 'Open Crew Assignment');

        // High-severity clashes nobody accepted, on these two days (missing crew is the block above).
        $ids = $movements->pluck('id')->flip();
        $clashes = collect($eventId ? $this->conflicts->forEvent($eventId) : [])
            ->filter(fn ($c) => ! $c['accepted'] && $c['sev'] === 'high' && $c['type'] !== 'Unassigned Resource'
                && collect($c['movement_ids'])->contains(fn ($id) => $ids->has($id)))
            ->values();
        $blocks[] = $this->block('conflicts', 'Unresolved conflicts', $clashes->count(),
            $clashes->take(self::EXAMPLES)->map(fn ($c) => trim(($c['when'] ? $c['when'].' · ' : '').$c['type'].': '.$c['text']))->all(),
            url('/plans?tab=conflicts'), 'Open the Conflicts tab');

        $noJob = $movements->filter(fn (Movement $m) => $m->job === null);
        $blocks[] = $this->block('jobs', 'Movements with no job yet', $noJob->count(),
            $noJob->take(self::EXAMPLES)->map(fn (Movement $m) => $this->movementLine($m))->values()->all(),
            url('/plans'), 'Open Planning');

        $issues = JobIssue::open()->with(['job:id,job_id,team_id', 'job.team:id,code'])
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
            ->oldest()->get();
        $blocks[] = $this->block('issues', 'Open issues', $issues->count(),
            $issues->take(self::EXAMPLES)->map(fn (JobIssue $i) => implode(' · ', array_filter([
                $i->label(), $i->job?->job_id, $i->job?->team?->code, 'open '.$this->age($i->created_at),
            ])))->all(),
            url('/jobs'), 'Open the Jobs Queue');

        return array_values(array_filter($blocks, fn ($b) => $b['count'] > 0));
    }

    private function block(string $key, string $title, int $count, array $rows, string $link, string $linkLabel): array
    {
        return compact('key', 'title', 'count', 'rows', 'link') + ['linkLabel' => $linkLabel];
    }

    private function movementLine(Movement $m): string
    {
        return implode(' · ', array_filter([
            $m->window_start?->format('D j M H:i'),
            $m->team?->code,
            ($m->from_location ?? '—').' → '.($m->to_location ?? '—'),
        ]));
    }

    private function age(?Carbon $since): string
    {
        $minutes = $since ? (int) $since->diffInMinutes(now(), true) : 0;
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);

        return $days > 0 ? "{$days} d {$hours} h" : ($hours > 0 ? "{$hours} h" : ($minutes % 60).' min');
    }

    /** D−1 judged on its own: jobs of movements that started that day, checkpoints scheduled that day. */
    private function yesterday(?int $eventId, Carbon $day): array
    {
        $next = $day->copy()->addDay();
        $scope = fn ($q) => $q->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId));

        $jobs = fn () => JobOperation::query()->where($scope)->where('status', '!=', 'cancelled')
            ->whereHas('movement', fn ($q) => $q->where('window_start', '>=', $day)->where('window_start', '<', $next));
        $checkpoints = fn () => JobCheckpoint::query()->where($scope)->where('scheduled_at', '>=', $day)->where('scheduled_at', '<', $next);

        $judged = $checkpoints()->where('state', 'done')->whereNotNull('is_on_time')->count();
        $onTime = $checkpoints()->where('state', 'done')->where('is_on_time', true)->count();
        $avgDelay = $checkpoints()->where('state', 'done')->where('delay_minutes', '>', 0)->avg('delay_minutes');

        return [
            'date' => $day->toDateString(),
            'jobsPlanned' => $jobs()->count(),
            'jobsCompleted' => $jobs()->where('status', 'completed')->count(),
            'onTimePct' => $judged > 0 ? (int) round($onTime / $judged * 100) : null,
            'judged' => $judged,
            'avgDelay' => $avgDelay !== null ? (int) round($avgDelay) : null,
            'skipped' => $checkpoints()->where('state', 'skipped')->count(),
            'issuesRaised' => JobIssue::query()->where($scope)->where('created_at', '>=', $day)->where('created_at', '<', $next)->count(),
            'issuesResolved' => JobIssue::query()->where($scope)->where('resolved_at', '>=', $day)->where('resolved_at', '<', $next)->count(),
        ];
    }

    private function today(?int $eventId, Carbon $day): array
    {
        $starts = Movement::query()
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
            ->where('status', '!=', 'cancelled')
            ->where('window_start', '>=', $day)
            ->where('window_start', '<', $day->copy()->addDay())
            ->orderBy('window_start')
            ->pluck('window_start');

        $byHour = $starts->countBy(fn (Carbon $t) => $t->format('H'));
        $busiest = $byHour->sortDesc()->keys()->first();

        return [
            'movements' => $starts->count(),
            'first' => $starts->first()?->format('H:i'),
            'last' => $starts->last()?->format('H:i'),
            'busiestHour' => $busiest !== null ? sprintf('%02d:00–%02d:00', $busiest, ((int) $busiest + 1) % 24) : null,
            'busiestCount' => $busiest !== null ? $byHour[$busiest] : 0,
            'link' => url('/day-board?date='.$day->toDateString()),
        ];
    }

    /** D's movements, with the movement's own crew (set before any job exists). */
    private function schedule(?int $eventId, Carbon $day): array
    {
        return Movement::with(['team:id,code,team_name', 'job:id,movement_id,job_id,status', 'vehicle:id,code,plate_number,vehicle_type', 'units:id,movement_id,vehicle_id'])
            ->when($eventId !== null, fn ($q) => $q->where('event_id', $eventId))
            ->where('window_start', '>=', $day)
            ->where('window_start', '<', $day->copy()->addDay())
            ->orderBy('window_start')
            ->get()
            ->map(function (Movement $movement) {
                $job = $movement->job;
                $delay = (int) ($movement->delay_minutes ?? 0);
                $vehicle = $movement->vehicle ? ($movement->vehicle->code ?? $movement->vehicle->plate_number ?? $movement->vehicle->vehicle_type) : null;
                $extra = $movement->units->whereNotNull('vehicle_id')->count();

                return [
                    'id' => $job?->job_id ?? 'MV-'.$movement->id,
                    'code' => $movement->team?->code ?? '—',
                    'team' => $movement->team?->team_name ?? '—',
                    'from' => $movement->from_location ?? '—',
                    'to' => $movement->to_location ?? '—',
                    'dep' => $movement->window_start?->format('H:i') ?? '--:--',
                    'arr' => $movement->window_end?->format('H:i') ?? '--:--',
                    'vehicle' => $vehicle ? $vehicle.($extra ? " +{$extra}" : '') : 'Unassigned',
                    'status' => match (true) {
                        $movement->status === 'cancelled' => 'cancelled',
                        $delay > 0 => 'delayed',
                        $job?->status === 'completed' => 'done',
                        $job?->status === 'in-progress' => 'in-progress',
                        default => 'scheduled',
                    },
                    'delay' => $delay > 0 ? $delay : null,
                ];
            })
            ->all();
    }

    /** The event's chosen audience: active users with an email address. No audience = nobody. */
    private function recipients(?int $eventId): array
    {
        $event = $eventId ? Event::find($eventId) : null;

        return $event
            ? $event->dailyEmailRecipients()->active()->whereNotNull('email')->orderBy('name')->get(['users.id', 'users.name', 'users.email'])
                ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->all()
            : [];
    }
}
