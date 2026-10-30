<?php

namespace App\Services\Ai;

use App\Models\Movement;
use App\Models\Plan;
use App\Models\User;
use App\Services\ConflictDetectionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Where plans stand on the way to jobs, and what stops them, for Daleel. The step rules are those of
 * resources/js/Composables/planReadiness.js (keep the two in step); the per-movement blockers add what that
 * checklist doesn't show: no checkpoint template, a flight movement with no flight time, a match movement
 * with no kick-off.
 */
class PlanReadiness
{
    public const PLANS = 15;
    public const BLOCKERS = 10;

    public function __construct(private ConflictDetectionService $conflicts) {}

    /**
     * Plans with a movement (or a plan date) between $from and $to, or the one plan $reference names.
     */
    public function build(int $eventId, User $user, ?Carbon $from, ?Carbon $to, ?string $reference = null): array
    {
        if (! $user->can('plans.view')) {
            return ['error' => 'Plan readiness is only available to users who can view plans.'];
        }

        $visible = OperationsAttention::visibleMovements($eventId, $user)->select('movements.id');

        $plans = Plan::query()
            ->where('event_id', $eventId)
            ->when($reference, fn ($q) => $q->where(fn ($p) => $p->where('code', $reference)
                ->orWhere('name', 'like', '%'.$reference.'%')
                ->when(ctype_digit((string) $reference), fn ($n) => $n->orWhere('id', (int) $reference))))
            ->when(! $reference && $from && $to, fn ($q) => $q->where(fn ($p) => $p->whereBetween('date', [$from, $to])
                ->orWhereHas('movements', fn ($m) => $m->where('window_start', '>=', $from)->where('window_start', '<', $to))))
            ->with(['movements' => fn ($q) => $q->whereIn('movements.id', $visible)->where('status', '!=', 'cancelled')
                ->with(['team:id,team_name', 'job:id,job_id,movement_id,status', 'flight:id,scheduled_at', 'match:id,kick_off',
                    'checkpointTemplate' => fn ($t) => $t->withTrashed()->withCount('checkpoints')])
                ->orderBy('window_start')])
            ->orderBy('date')
            ->limit(100)
            ->get()
            ->filter(fn (Plan $p) => $p->movements->isNotEmpty() || $reference);

        $open = collect($this->conflicts->forEvent($eventId))
            // Missing crew is reported by the crew step and the blockers, so it isn't counted again as a conflict here.
            ->filter(fn ($c) => ! $c['accepted'] && $c['type'] !== 'Unassigned Resource');

        $rows = $plans->map(fn (Plan $plan) => $this->plan($plan, $open))
            ->sortBy(fn ($r) => [$r['status'] === 'blocked' ? 0 : ($r['status'] === 'partly ready' ? 1 : 2), $r['date']])
            ->values();

        return [
            'window' => $reference ? null : ['from' => $from?->toDateString(), 'to' => $to?->copy()->subDay()->toDateString()],
            'plans' => $rows->count(),
            'blocked' => $rows->where('status', 'blocked')->count(),
            'partly_ready' => $rows->where('status', 'partly ready')->count(),
            'ready_or_done' => $rows->whereIn('status', ['ready', 'all jobs generated'])->count(),
            'items' => $rows->take(self::PLANS)->all(),
            'note' => 'A movement can generate a job once it has a vehicle and a supervisor (the Generate jobs rule). Blockers are listed for movements that have no job yet, capped at '.self::BLOCKERS.' per plan.',
        ];
    }

    private function plan(Plan $plan, Collection $openConflicts): array
    {
        $movements = $plan->movements;
        $ids = $movements->pluck('id')->flip();
        $conflicts = $openConflicts->filter(fn ($c) => collect($c['movement_ids'])->contains(fn ($id) => $ids->has($id)));

        $total = $movements->count();
        $untimed = $movements->filter(fn (Movement $m) => ! $m->window_start)->count();
        $crewed = $movements->filter(fn (Movement $m) => $m->vehicle_id && $m->driver_id && $m->field_supervisor_id)->count();
        $withJobs = $movements->filter(fn (Movement $m) => $m->job !== null)->count();
        $ready = $movements->filter(fn (Movement $m) => ! $m->job && $m->vehicle_id && $m->field_supervisor_id && ! $m->jobBlocker())->count();

        $blockers = $movements->filter(fn (Movement $m) => ! $m->job)
            ->map(fn (Movement $m) => [$m, $this->blockersFor($m, $conflicts)])
            ->filter(fn ($pair) => $pair[1] !== [])
            ->values();

        $status = match (true) {
            $total > 0 && $withJobs === $total => 'all jobs generated',
            $blockers->isEmpty() && $total > 0 => 'ready',
            $ready > 0 => 'partly ready',
            default => 'blocked',
        };

        return [
            'code' => $plan->code,
            'name' => $plan->name,
            'date' => $plan->date?->toDateString(),
            'url' => '/plans?plan='.$plan->id,
            'status' => $status,
            'movements' => $total,
            'untimed' => $untimed,
            'open_conflicts' => $conflicts->count(),
            'high_conflicts' => $conflicts->where('sev', 'high')->count(),
            'crewed' => $crewed,
            'with_jobs' => $withJobs,
            'ready_for_jobs' => $ready,
            'blocked_movements' => $blockers->count(),
            'next_step' => match (true) {
                $total === 0 => 'Add movements',
                $untimed > 0 => 'Give every movement a time',
                $conflicts->isNotEmpty() => 'Resolve conflicts (Planning → Conflicts)',
                $crewed < $total => 'Assign crew (Crew Assignment)',
                $withJobs < $total => 'Generate jobs',
                default => null,
            },
            'blockers' => $blockers->take(self::BLOCKERS)->map(fn ($pair) => [
                'movement_code' => $pair[0]->code,
                'team' => $pair[0]->team?->team_name,
                'time' => $pair[0]->window_start?->format('D j M H:i'),
                'problems' => $pair[1],
                'url' => '/plans?plan='.$plan->id.'&movement='.$pair[0]->id,
            ])->all(),
        ];
    }

    /** What stops (or should stop) this movement from getting a sound job. */
    private function blockersFor(Movement $m, Collection $conflicts): array
    {
        $flightKind = in_array($m->kind, ['arrival', 'departure'], true) && $m->flight_id;

        return array_values(array_filter([
            in_array($m->kind, ['arrival', 'departure'], true) && ! $m->flight_id ? "no {$m->kind} flight (removed or never added in Event Teams); jobs can't be generated" : null,
            ! $m->window_start ? 'no time' : null,
            ! $m->vehicle_id ? 'no vehicle' : null,
            ! $m->field_supervisor_id ? 'no supervisor' : null,
            ! $m->driver_id ? 'no driver' : null,
            ! $m->checkpoint_template_id ? 'no checkpoint template (the job would have no steps)' : null,
            $m->checkpointTemplate?->trashed() ? "checkpoint template {$m->checkpointTemplate->code} is in the recycle bin" : null,
            $m->checkpointTemplate && ! $m->checkpointTemplate->trashed() && ! $m->checkpointTemplate->checkpoints_count
                ? "checkpoint template {$m->checkpointTemplate->code} has no steps" : null,
            $flightKind && ! $m->flight?->scheduled_at ? 'flight has no scheduled time' : null,
            $m->match_id && ! $m->match?->kick_off ? 'match has no kick-off time' : null,
            ($n = $conflicts->filter(fn ($c) => $c['sev'] === 'high' && in_array($m->id, $c['movement_ids'], true))->count())
                ? "{$n} open high conflict(s)" : null,
        ]));
    }
}
