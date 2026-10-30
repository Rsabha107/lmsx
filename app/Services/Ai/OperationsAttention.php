<?php

namespace App\Services\Ai;

use App\Models\JobCheckpoint;
use App\Models\JobIssue;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\User;
use App\Services\ConflictDetectionService;
use App\Services\OperationsQueryService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Daleel's shift briefing: what needs attention in a time window, for what the user may see. Read-only and
 * deterministic; the assistant only summarises it. Counts are complete, item lists are capped.
 * (The Daily Email has its own, unscoped list in DailySummaryService.)
 */
class OperationsAttention
{
    public const ITEMS = 10;

    /** Categories, most urgent first. */
    private const TITLES = [
        'overdue' => 'Overdue checkpoint updates',
        'delayed' => 'Delayed movements',
        'conflicts' => 'Unresolved high conflicts',
        'crew' => 'Missing crew',
        'issues' => 'Open issues',
        'no_job' => 'No job yet',
    ];

    public function __construct(private ConflictDetectionService $conflicts, private \App\Services\SettingsService $settings) {}

    /** Movements of the event the user may see (same rules as the other Daleel tools). */
    public static function visibleMovements(int $eventId, User $user): Builder
    {
        $query = Movement::query()->where('movements.event_id', $eventId)->where('movements.status', '!=', 'cancelled');

        if ($user->can('movements.view')) {
            return $query->when(! $user->can('movements.view-all-functional-areas'),
                fn ($q) => $q->whereIn('movements.functional_area', $user->functionalAreaCodes()));
        }

        if ($user->can('jobs.view')) {
            return $query->whereIn('movements.id', JobOperation::visibleTo($user, $eventId)->select('movement_id'));
        }

        return $query->whereRaw('1 = 0');
    }

    public function build(int $eventId, User $user, Carbon $from, Carbon $to): array
    {
        $movements = self::visibleMovements($eventId, $user)
            ->where('window_start', '<', $to)
            ->where(fn ($q) => $q->where('window_end', '>', $from)->orWhere(fn ($n) => $n->whereNull('window_end')->where('window_start', '>=', $from)))
            ->whereDoesntHave('job', fn ($q) => $q->whereIn('status', ['completed', 'cancelled']))
            ->with(['team:id,team_name,code', 'job', 'job.checkpoints' => fn ($q) => $q->overdue()->orderBy('scheduled_at')])
            ->orderBy('window_start')
            ->get();

        $item = fn (Movement $m, string $reason) => [
            'time' => $m->window_start?->format('H:i').'–'.$m->window_end?->format('H:i'),
            'date' => $m->window_start?->toDateString(),
            'starts_in_minutes' => $m->window_start ? (int) round(now()->diffInMinutes($m->window_start, false)) : null,
            'team' => $m->team?->team_name,
            'movement_code' => $m->code,
            'job_id' => $m->job?->job_id,
            'kind' => $m->kind,
            'route' => ($m->from_location ?? '?').' → '.($m->to_location ?? '?'),
            'reason' => $reason,
            'url' => OperationsQueryService::movementUrl($m),
        ];

        $found = [
            'overdue' => $movements->filter(fn (Movement $m) => $m->job && in_array($m->job->status, ['dispatched', 'in-progress'], true) && $m->job->checkpoints->isNotEmpty())
                ->map(function (Movement $m) use ($item) {
                    /** @var JobCheckpoint $cp */
                    $cp = $m->job->checkpoints->first();
                    $late = (int) round($cp->scheduled_at->diffInMinutes(now()));

                    return $item($m, "\"{$cp->name}\" was due {$cp->scheduled_at->format('H:i')}, no update for {$late} min");
                }),
            'delayed' => $movements->filter(fn (Movement $m) => (int) $m->delay_minutes > 0)
                ->sortByDesc('delay_minutes')
                ->map(fn (Movement $m) => $item($m, "running {$m->delay_minutes} min late")),
            'conflicts' => $this->openConflicts($eventId, $movements),
            'crew' => $movements->filter(fn (Movement $m) => ! $m->vehicle_id || ! $m->driver_id || ! $m->field_supervisor_id)
                ->map(fn (Movement $m) => $item($m, 'no '.implode(', ', array_keys(array_filter([
                    'vehicle' => ! $m->vehicle_id, 'driver' => ! $m->driver_id, 'supervisor' => ! $m->field_supervisor_id,
                ]))))),
            'issues' => $this->openIssues($eventId, $user),
            // Same tiers as the data checks: urgent within no_job_urgent_hours (Settings → Conflict rules).
            'no_job' => $movements->filter(fn (Movement $m) => $m->job === null)
                ->map(fn (Movement $m) => $item($m, 'planned, job not generated') + [
                    'severity' => $m->window_start && $m->window_start->lte(now()->addHours($this->settings->getThreshold('no_job_urgent_hours'))) ? 'high' : 'medium',
                ])
                ->sortBy(fn ($i) => $i['severity'] === 'high' ? 0 : 1),
        ];

        $categories = collect(self::TITLES)->map(fn (string $title, string $key) => [
            'key' => $key,
            'title' => $title,
            'count' => $found[$key]->count(),
            'items' => $found[$key]->take(self::ITEMS)->values()->all(),
        ])->filter(fn ($c) => $c['count'] > 0)->values()->all();

        return [
            'window' => ['from' => $from->format('Y-m-d H:i'), 'to' => $to->format('Y-m-d H:i')],
            'movements_in_window' => $movements->count(),
            'needs_attention' => array_sum(array_column($categories, 'count')),
            'categories' => $categories,
            'note' => 'Categories are in priority order. A movement can appear in more than one category; counts per category are complete, items are capped at '.self::ITEMS.'. Open issues are listed whatever their time.',
        ];
    }

    /** High-severity conflicts nobody accepted that touch a movement in the window (missing crew is its own category). */
    private function openConflicts(int $eventId, Collection $movements): Collection
    {
        if ($movements->isEmpty()) {
            return collect();
        }
        $byId = $movements->keyBy('id');

        return collect($this->conflicts->forEvent($eventId))
            ->filter(fn ($c) => ! $c['accepted'] && $c['sev'] === 'high' && $c['type'] !== 'Unassigned Resource'
                && collect($c['movement_ids'])->contains(fn ($id) => $byId->has($id)))
            ->map(function ($c) use ($byId) {
                $mine = collect($c['movement_ids'])->map(fn ($id) => $byId->get($id))->filter()->values();

                return [
                    'when' => $c['when'],
                    'type' => $c['type'],
                    'reason' => $c['text'],
                    'hint' => $c['hint'] ?? null,
                    'movement_codes' => $mine->pluck('code')->all(),
                    'url' => '/plans?tab=conflicts',
                    'movement_url' => $mine->isNotEmpty() ? OperationsQueryService::movementUrl($mine->first()) : null,
                ];
            })
            ->values();
    }

    private function openIssues(int $eventId, User $user): Collection
    {
        if (! $user->can('jobs.view')) {
            return collect();
        }

        return JobIssue::open()
            ->where('event_id', $eventId)
            ->whereIn('job_id', JobOperation::visibleTo($user, $eventId)->select('id'))
            ->with(['job:id,job_id,team_id', 'job.team:id,team_name'])
            ->oldest()
            ->get()
            ->map(fn (JobIssue $i) => [
                'job_id' => $i->job?->job_id,
                'team' => $i->job?->team?->team_name,
                'reason' => $i->label(),
                'open_since' => $i->created_at?->format('D j M H:i'),
                'url' => $i->job ? OperationsQueryService::jobUrl($i->job) : null,
            ]);
    }
}
