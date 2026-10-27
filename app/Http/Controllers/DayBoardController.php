<?php

namespace App\Http\Controllers;

use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use App\Support\ScheduleRows;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A supervisor's day: every job starting that day, with the step each one is
 * on and the step before it. Attention flags are worked out in the page so
 * they stay live as the clock moves.
 */
class DayBoardController extends Controller
{
    private const SETTLED = ['done', 'skipped'];

    public function __invoke(Request $request): Response
    {
        try {
            $day = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        } catch (\Exception) {
            $day = now()->startOfDay();
        }

        $view = $request->query('view') === 'schedule' ? 'schedule' : 'board';
        // The active event plus any others picked here (all accessible), like Crew Assignment.
        $eventIds = $request->session()->get('active_event_id')
            ? \App\Support\EventSelection::ids($request, 'dayboard_event_ids')
            : [];

        $jobs = $eventIds
            ? $this->scoped($request, $eventIds)
                ->whereHas('movement', fn ($q) => $q->where('window_start', '>=', $day)->where('window_start', '<', $day->copy()->addDay()))
                ->with([
                    'event:id,name,short_name',
                    'movement:id,team_id,kind,from_location,to_location,window_start,window_end,delay_minutes,passengers',
                    'movement.team:id,code,team_name,country_id,flag',
                    'vehicle:id,code,plate_number,vehicle_type',
                    'driver:id,name,phone',
                    'supervisor:id,name',
                    'checkpoints:id,job_id,order,name,state,scheduled_at,completed_at,skipped_at,skip_reason,is_on_time,completed_by',
                    'checkpoints.completedBy:id,name',
                    'issues' => fn ($q) => $q->whereNull('resolved_at'),
                ])
                ->get()
                ->map(fn (JobOperation $job) => $this->present($job, $request))
                ->sortBy('start')
                ->values()
            : collect();

        return Inertia::render('DayBoard', [
            'date' => $day->toDateString(),
            'view' => $view,
            'jobs' => $jobs,
            'schedule' => $view === 'schedule' && $eventIds ? $this->scheduleRows($request, $eventIds, $day) : [],
            'dayCounts' => $eventIds ? $this->dayCounts($request, $eventIds) : [],
            'eventIds' => $eventIds,
            'events' => \App\Support\EventSelection::options($request->user(), $eventIds[0] ?? null),
        ]);
    }

    /** The Movement Schedule rows for the day, limited to what this user may see. */
    private function scheduleRows(Request $request, array $eventIds, Carbon $day): array
    {
        return $this->scoped($request, $eventIds, 'jobs_operations.')
            ->with(ScheduleRows::RELATIONS)
            ->join('movements', 'jobs_operations.movement_id', '=', 'movements.id')
            ->where('movements.window_start', '>=', $day)
            ->where('movements.window_start', '<', $day->copy()->addDay())
            ->orderBy('movements.window_start')
            ->select('jobs_operations.*')
            ->get()
            ->map(fn (JobOperation $job) => ScheduleRows::present($job))
            ->all();
    }

    /** Jobs the user may see: active event, their functional areas, and only their own unless they may see others'. */
    private function scoped(Request $request, array $eventIds, string $prefix = ''): Builder
    {
        $user = $request->user();
        // The agency reads every crew's jobs without holding job permissions; ProviderScope still applies.
        $agency = $user->hasRole('agency');

        return JobOperation::query()
            ->whereIn($prefix.'event_id', $eventIds)
            ->where($prefix.'status', '!=', 'cancelled')
            ->when(! $agency && ! $user->can('jobs.view-all-functional-areas'),
                fn ($q) => $q->whereIn($prefix.'functional_area', $user->functionalAreaCodes()))
            ->when(! $agency && ! $user->can('jobs.view-unassigned'), fn ($q) => $q->supervisedBy($user->id, $prefix));
    }

    /** @return array<string, int> jobs per day across the event, for the week strip and calendar */
    private function dayCounts(Request $request, array $eventIds): array
    {
        return $this->scoped($request, $eventIds, 'jobs_operations.')
            ->join('movements', 'movements.id', '=', 'jobs_operations.movement_id')
            ->whereNotNull('movements.window_start')
            ->selectRaw('DATE(movements.window_start) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    private function present(JobOperation $job, Request $request): array
    {
        $movement = $job->movement;
        $team = $movement?->team;
        $checkpoints = $job->checkpoints->sortBy('order')->values();

        $currentIndex = $checkpoints->search(fn (JobCheckpoint $c) => ! in_array($c->state, self::SETTLED, true));
        $current = $currentIndex === false ? null : $checkpoints[$currentIndex];
        $previous = $currentIndex === false ? $checkpoints->last() : ($currentIndex > 0 ? $checkpoints[$currentIndex - 1] : null);
        $next = $currentIndex === false ? null : $checkpoints->get($currentIndex + 1);

        $stamp = fn (?Carbon $t) => $t?->format('Y-m-d H:i');

        return [
            'id' => $job->id,
            'job_id' => $job->job_id ?? 'J-'.$job->id,
            'url' => match (true) {
                $request->user()->can('console.view') => "/job/{$job->id}",
                $request->user()->can('jobs.view') => '/jobs/mobile/'.($job->job_id ?? $job->id),
                default => null,
            },
            'status' => $job->status,
            'status_label' => JobOperation::statusLabel($job->status),
            'kind' => $movement?->kind,
            'team' => $team?->team_name,
            'team_code' => $team?->code,
            // Shown when several events are on the board.
            'event' => $job->event?->short_name ?: $job->event?->name,
            'country_code' => $team?->country_id,
            'flag' => $team?->flag,
            'from' => $movement?->from_location,
            'to' => $movement?->to_location,
            'pax' => $movement?->passengers,
            'start' => $stamp($checkpoints->first()?->scheduled_at ?? $movement?->window_start),
            'end' => $stamp($checkpoints->last()?->scheduled_at ?? $movement?->window_end),
            'vehicle' => $job->vehicle ? ($job->vehicle->code ?? $job->vehicle->plate_number ?? $job->vehicle->vehicle_type) : null,
            'driver' => $job->driver?->name,
            'driver_phone' => $job->driver?->phone,
            'supervisor' => $job->supervisor?->name,
            'issues' => $job->issues->map(fn ($i) => ['label' => $i->label(), 'severity' => $i->severity])->values(),
            'steps' => $checkpoints->map(fn (JobCheckpoint $c) => ['name' => $c->name, 'state' => $c->state])->values(),
            'current_index' => $currentIndex === false ? null : $currentIndex,
            'current' => $current ? [
                'name' => $current->name,
                'scheduled' => $stamp($current->scheduled_at),
            ] : null,
            'previous' => $previous ? [
                'name' => $previous->name,
                'state' => $previous->state,
                'scheduled' => $stamp($previous->scheduled_at),
                'completed' => $stamp($previous->completed_at ?? $previous->skipped_at),
                'delay' => $previous->delay_minutes,
                'on_time' => $previous->is_on_time,
                'skip_reason' => $previous->skip_reason,
                'by' => $previous->completedBy?->name,
            ] : null,
            'next' => $next ? ['name' => $next->name, 'scheduled' => $stamp($next->scheduled_at)] : null,
        ];
    }
}
