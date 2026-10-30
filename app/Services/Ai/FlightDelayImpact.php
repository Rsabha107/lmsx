<?php

namespace App\Services\Ai;

use App\Models\Movement;
use App\Models\TeamFlight;
use App\Models\User;
use App\Services\ConflictDetectionService;
use App\Services\OperationsQueryService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * What a late flight puts at risk, for Daleel. The flight's movements (and their job steps) are moved by the
 * delay in memory, the conflict checks run on that copy (`ConflictDetectionService::whatIf`), and the result
 * is compared with today's checks: what is newly at risk (the team's next movement, crew turnarounds and
 * double-bookings, driver duty, hotel stay, kick-off margins). Nothing is saved.
 */
class FlightDelayImpact
{
    /** Configuration checks, not operational risk: a delay shifts the window and its reference together. */
    private const IGNORED = ['Window Out Of Sync With Settings', 'Unassigned Resource'];

    public function __construct(private ConflictDetectionService $conflicts) {}

    /** The flight the user means: by number, preferring the one nearest now (optionally on a date). */
    public static function findFlight(int $eventId, string $number, ?string $date = null): Collection
    {
        $number = strtoupper(str_replace(' ', '', $number));

        return TeamFlight::with('team:id,code,team_name')
            ->where('event_id', $eventId)
            ->whereRaw("REPLACE(UPPER(flight_number), ' ', '') = ?", [$number])
            ->when($date, fn ($q) => $q->whereDate('scheduled_at', $date))
            ->get()
            ->sortBy(fn (TeamFlight $f) => abs(now()->diffInMinutes($f->scheduled_at ?? now(), false)))
            ->values();
    }

    /** The delay the flight is running now (estimated vs scheduled), when known. */
    public static function liveDelay(TeamFlight $flight): ?int
    {
        if ($flight->scheduled_at && $flight->estimated_at) {
            return (int) round($flight->scheduled_at->diffInMinutes($flight->estimated_at, false));
        }

        return $flight->delay_minutes ?: null;
    }

    public function build(int $eventId, User $user, TeamFlight $flight, int $delay): array
    {
        if (! $user->can('movements.view')) {
            return ['error' => 'Impact analysis is only available to users who can view movements.'];
        }

        $visible = OperationsAttention::visibleMovements($eventId, $user)->pluck('movements.id')->flip();
        $direct = Movement::with(['team:id,team_name', 'job:id,job_id,movement_id,status', 'vehicle:id,code', 'driver:id,name', 'fieldSupervisor:id,name'])
            ->where('event_id', $eventId)->where('flight_id', $flight->id)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->orderBy('window_start')->get();

        $relevant = fn (array $c) => ! $c['accepted'] && ! in_array($c['type'], self::IGNORED, true)
            && collect($c['movement_ids'])->contains(fn ($id) => $visible->has($id));
        $before = collect($this->conflicts->forEvent($eventId))->filter($relevant)->keyBy('id');
        $after = collect($this->conflicts->whatIf($eventId, fn (Collection $movements) => $this->shift($movements, $flight->id, $delay)))
            ->filter($relevant)->keyBy('id');

        $describe = fn (array $c) => [
            'severity' => $c['sev'],
            'type' => $c['type'],
            'when' => $c['when'],
            'fact' => $c['text'],
            'hint' => $c['hint'] ?? null,
            'affects' => $c['affects'],
            'url' => '/plans?tab=conflicts',
        ];
        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];

        $new = $after->diffKeys($before)->sortBy(fn ($c) => $rank[$c['sev']])->map($describe)->values();
        $worse = $after->intersectByKeys($before)
            ->filter(fn ($c, $id) => $c['text'] !== $before[$id]['text'] || $rank[$c['sev']] < $rank[$before[$id]['sev']])
            ->map(fn ($c, $id) => $describe($c) + ['before' => $before[$id]['text']])->values();
        $resolved = $before->diffKeys($after)->map(fn ($c) => ['type' => $c['type'], 'fact' => $c['text']])->values();

        return [
            'flight' => [
                'number' => $flight->flight_number,
                'team' => $flight->team?->team_name,
                'direction' => $flight->direction,
                'scheduled' => $flight->scheduled_at?->format('D j M H:i'),
                'assumed' => $flight->scheduled_at?->copy()->addMinutes($delay)->format('D j M H:i'),
                'delay_minutes' => $delay,
                'live_delay_minutes' => self::liveDelay($flight),
                'url' => '/event-teams',
            ],
            'moved_movements' => $direct->map(fn (Movement $m) => [
                'movement_code' => $m->code,
                'job_id' => $m->job?->job_id,
                'team' => $m->team?->team_name,
                'kind' => $m->kind,
                'route' => ($m->from_location ?? '?').' → '.($m->to_location ?? '?'),
                'window_now' => $m->window_start?->format('H:i').'–'.$m->window_end?->format('H:i'),
                'window_if_late' => $m->window_start?->copy()->addMinutes($delay)->format('D H:i').'–'.$m->window_end?->copy()->addMinutes($delay)->format('H:i'),
                'crew' => array_values(array_filter([$m->vehicle?->code, $m->driver?->name, $m->fieldSupervisor?->name])),
                'url' => OperationsQueryService::movementUrl($m),
            ])->values()->all(),
            'newly_at_risk' => $new->count(),
            'new_risks' => $new->take(15)->all(),
            'worse' => $worse->take(10)->all(),
            'no_longer_a_problem' => $resolved->take(5)->all(),
            'note' => 'A what-if: the flight\'s movements and their job steps are moved by the delay and the conflict rules (Settings → Conflict rules) are run again; nothing is saved. Risks already there before the delay are not listed unless they get worse.',
        ];
    }

    /** Moves the flight and its movements (window and job steps) by $delay minutes, in memory only. */
    private function shift(Collection $movements, int $flightId, int $delay): void
    {
        $shiftedFlights = [];
        foreach ($movements->where('flight_id', $flightId) as $m) {
            $m->window_start = $m->window_start?->copy()->addMinutes($delay);
            $m->window_end = $m->window_end?->copy()->addMinutes($delay);
            foreach ($m->job?->checkpoints ?? [] as $cp) {
                $cp->scheduled_at = $cp->scheduled_at?->copy()->addMinutes($delay);
            }
            // The same flight model can hang off several movements: move it once.
            $flight = $m->flight;
            if ($flight && ! isset($shiftedFlights[spl_object_id($flight)])) {
                $shiftedFlights[spl_object_id($flight)] = true;
                $new = $flight->scheduled_at?->copy()->addMinutes($delay);
                $flight->scheduled_at = $new;
                $flight->estimated_at = $new;
            }
        }
    }
}
