<?php

namespace App\Services\Ai;

use App\Models\Driver;
use App\Models\Movement;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ConflictDetectionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Vehicle, driver and supervisor demand against what the event has, per day, for Daleel. Required = one per
 * movement (vehicles and drivers also count each extra unit); peak = most needed at the same moment, the
 * fewest that could cover the day if each were free in time. Clashes come from the conflict rules.
 */
class ResourceBottlenecks
{
    public const MAX_DAYS = 14;
    public const ITEMS = 10;

    private const CLASHES = [
        'vehicle' => ['Vehicle Double-Booked'],
        'driver' => ['Driver Double-Booked', 'Driver Shift Too Long', 'Insufficient Rest'],
        'supervisor' => ['Supervisor Double-Booked'],
    ];

    public function __construct(private ConflictDetectionService $conflicts) {}

    /** @param  'vehicle'|'driver'|'supervisor'|'all'  $type */
    public function build(int $eventId, User $user, Carbon $from, Carbon $to, string $type = 'all'): array
    {
        if (! $user->can('movements.view')) {
            return ['error' => 'Resource figures are only available to users who can view movements.'];
        }

        $types = $type === 'all' ? array_keys(self::CLASHES) : [$type];
        $movements = OperationsAttention::visibleMovements($eventId, $user)
            ->where('window_start', '>=', $from)
            ->where('window_start', '<', $to)
            ->whereDoesntHave('job', fn ($q) => $q->where('status', 'cancelled'))
            ->with(['team:id,team_name', 'fleetProvider:id,name', 'units:id,movement_id,vehicle_id,driver_id', 'extraSupervisors:users.id'])
            ->orderBy('window_start')
            ->get();

        $pools = [
            'vehicle' => Vehicle::inEventPool($eventId)->count(),
            'driver' => Driver::inEventPool($eventId)->count(),
            'supervisor' => User::fieldSupervisors($user)->whereHas('events', fn ($q) => $q->where('events.id', $eventId))->count(),
        ];

        $ids = $movements->pluck('id')->flip();
        $clashes = $movements->isEmpty() ? collect() : collect($this->conflicts->forEvent($eventId))
            ->filter(fn ($c) => ! $c['accepted'] && collect($c['movement_ids'])->contains(fn ($id) => $ids->has($id)));

        $days = [];
        foreach ($movements->groupBy(fn (Movement $m) => $m->window_start->toDateString()) as $day => $list) {
            $row = ['date' => $day, 'movements' => $list->count()];
            foreach ($types as $t) {
                $row[$t] = $this->resource($t, $list, $pools[$t], $clashes, $day);
            }
            $days[] = $row;
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->copy()->subDay()->toDateString(),
            'pools' => array_intersect_key($pools, array_flip($types)),
            'days' => $days,
            'by_provider' => $this->breakdown($movements, $types, fn (Movement $m) => $m->fleetProvider?->name ?? 'No provider', $this->providerPools($eventId)),
            'by_functional_area' => $this->breakdown($movements, $types, fn (Movement $m) => $m->functional_area ?: 'None'),
            'note' => 'by_provider / by_functional_area: totals over the range, and the worst day\'s peak (with the provider\'s own vehicle / driver pool when it has one). Only days with movements are listed. required = one per movement (vehicles and drivers also count extra units); assigned = distinct resources on those movements; peak_at_once = most needed at the same moment; short_at_peak = peak minus the pool when the pool is smaller (0 when the pool is empty, i.e. not set up).',
        ];
    }

    private function resource(string $type, Collection $movements, int $pool, Collection $clashes, string $day): array
    {
        $need = fn (Movement $m) => $type === 'supervisor' ? 1 : 1 + $m->units->count();
        $key = ['vehicle' => 'vehicle_id', 'driver' => 'driver_id', 'supervisor' => 'field_supervisor_id'][$type];
        $missing = $movements->filter(fn (Movement $m) => ! $m->$key);

        [$peak, $peakAt] = self::peak($movements, $need);

        $ids = $movements->pluck('id')->flip();
        $own = $clashes->filter(fn ($c) => in_array($c['type'], self::CLASHES[$type], true)
            && collect($c['movement_ids'])->contains(fn ($id) => $ids->has($id)));

        return [
            'required' => $movements->sum($need),
            'assigned' => $movements->flatMap(fn (Movement $m) => $m->resourceIds($key))->unique()->count(),
            'movements_without' => $missing->count(),
            'peak_at_once' => $peak,
            'peak_time' => $peakAt?->format('H:i'),
            'short_at_peak' => $pool > 0 ? max(0, $peak - $pool) : 0,
            'clashes' => $own->count(),
            'clash_examples' => $own->take(5)->map(fn ($c) => trim(($c['when'] ? $c['when'].' · ' : '').$c['type'].': '.$c['text']))->values()->all(),
            'without' => $missing->take(self::ITEMS)->map(fn (Movement $m) => [
                'movement_code' => $m->code,
                'team' => $m->team?->team_name,
                'window' => $m->window_start?->format('H:i').'–'.$m->window_end?->format('H:i'),
                'route' => "{$m->from_location} → {$m->to_location}",
            ])->values()->all(),
            'url' => '/crew-assignment?date='.$day,
        ];
    }

    /** Most needed at the same moment, and when: a sweep over the movement windows. */
    private static function peak(Collection $movements, callable $need): array
    {
        $events = $movements->flatMap(fn (Movement $m) => [
            [$m->window_start, $need($m)],
            [$m->window_end ?? $m->window_start->copy()->addMinutes(30), -$need($m)],
        ])->sortBy(fn ($e) => [$e[0]->timestamp, $e[1]])->values();
        $running = $peak = 0;
        $peakAt = null;
        foreach ($events as [$at, $delta]) {
            $running += $delta;
            if ($running > $peak) {
                [$peak, $peakAt] = [$running, $at];
            }
        }

        return [$peak, $peakAt];
    }

    /** Vehicles and drivers in the event's pool, per provider name. */
    private function providerPools(int $eventId): array
    {
        $count = fn ($query) => $query->with('provider:id,name')->get(['id', 'provider_id'])
            ->countBy(fn ($r) => $r->provider?->name ?? 'No provider')->all();

        return ['vehicle' => $count(Vehicle::inEventPool($eventId)), 'driver' => $count(Driver::inEventPool($eventId))];
    }

    /** Totals per group (provider, functional area) over the range, with each role's worst-day peak. */
    private function breakdown(Collection $movements, array $types, callable $group, array $pools = []): array
    {
        $key = ['vehicle' => 'vehicle_id', 'driver' => 'driver_id', 'supervisor' => 'field_supervisor_id'];

        return $movements->groupBy($group)->map(function (Collection $list, string $name) use ($types, $key, $pools) {
            $row = ['name' => $name, 'movements' => $list->count()];
            foreach ($types as $t) {
                $need = fn (Movement $m) => $t === 'supervisor' ? 1 : 1 + $m->units->count();
                $worst = $list->groupBy(fn (Movement $m) => $m->window_start->toDateString())
                    ->map(fn (Collection $day, string $date) => [$date, self::peak($day, $need)])
                    ->sortByDesc(fn ($d) => $d[1][0])->first();
                $pool = $pools[$t][$name] ?? null;
                $row[$t] = [
                    'required' => $list->sum($need),
                    'movements_without' => $list->filter(fn (Movement $m) => ! $m->{$key[$t]})->count(),
                    'worst_day' => $worst[0] ?? null,
                    'worst_day_peak' => $worst[1][0] ?? 0,
                ] + ($pool !== null ? ['pool' => $pool, 'short_on_worst_day' => max(0, ($worst[1][0] ?? 0) - $pool)] : []);
            }

            return $row;
        })->sortByDesc('movements')->values()->all();
    }
}
