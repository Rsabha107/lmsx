<?php

namespace App\Http\Controllers;

use App\Models\JobCheckpoint;
use App\Models\JobOperation;
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

        $eventId = $request->session()->get('active_event_id');

        $jobs = $eventId
            ? $this->scoped($request, $eventId)
                ->whereHas('movement', fn ($q) => $q->where('window_start', '>=', $day)->where('window_start', '<', $day->copy()->addDay()))
                ->with([
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
            'jobs' => $jobs,
            'dayCounts' => $eventId ? $this->dayCounts($request, $eventId) : [],
        ]);
    }

    /** Jobs the user may see: active event, their functional areas, and only their own unless they may see others'. */
    private function scoped(Request $request, int $eventId, string $prefix = ''): Builder
    {
        $user = $request->user();

        return JobOperation::query()
            ->where($prefix.'event_id', $eventId)
            ->where($prefix.'status', '!=', 'cancelled')
            ->when(! $user->can('jobs.view-all-functional-areas'),
                fn ($q) => $q->whereIn($prefix.'functional_area', $user->functionalAreaCodes()))
            ->when(! $user->can('jobs.view-unassigned'), fn ($q) => $q->supervisedBy($user->id, $prefix));
    }

    /** @return array<string, int> jobs per day across the event, for the week strip and calendar */
    private function dayCounts(Request $request, int $eventId): array
    {
        return $this->scoped($request, $eventId, 'jobs_operations.')
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
            'url' => $request->user()->can('console.view') ? "/job/{$job->id}" : '/jobs/mobile/'.($job->job_id ?? $job->id),
            'status' => $job->status,
            'status_label' => JobOperation::statusLabel($job->status),
            'kind' => $movement?->kind,
            'team' => $team?->team_name,
            'team_code' => $team?->code,
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
