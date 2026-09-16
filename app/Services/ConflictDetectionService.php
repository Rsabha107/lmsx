<?php

namespace App\Services;

use App\Models\Movement;
use App\Models\TeamStay;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Scans an event's planned movements and reports scheduling problems
 * (resource clashes, impossible timings against flights/matches/hotel stays,
 * missing assignments) for the Planning > Conflicts tab.
 */
class ConflictDetectionService
{
    /** Minimum gap between two consecutive jobs for the same vehicle or driver. */
    private const TURNAROUND_MINUTES = 30;

    /** How long after landing a team may reasonably still be waiting airside. */
    private const PICKUP_GRACE_MINUTES = 90;

    /** How far a window may drift from its configured offset before it is worth reporting. */
    private const OFFSET_TOLERANCE_MINUTES = 60;

    /** Team must be at the stadium at least this long before kick-off. */
    private const KICKOFF_LEAD_MINUTES = 90;

    /** A driver's working day should not span longer than this. */
    private const DRIVER_SPAN_HOURS = 14;

    public function __construct(private SettingsService $settings)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forEvent(?int $eventId): array
    {
        if (!$eventId) {
            return [];
        }

        $movements = Movement::with([
                'plan:id,code,name,date',
                'team:id,code,team_name',
                'vehicle:id,code,capacity,status,vehicle_type',
                'driver:id,name,status',
                'fieldSupervisor:id,name',
                'flight:id,team_id,direction,flight_number,scheduled_at,estimated_at,party_size_total',
                'match:id,match_number,kick_off,venue_id',
                'match.venue:id,name',
                'checkpointTemplate.checkpoints:id,name',
            ])
            ->where('event_id', $eventId)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->orderBy('window_start')
            ->get();

        if ($movements->isEmpty()) {
            return [];
        }

        $conflicts = array_merge(
            $this->scheduleIntegrity($movements),
            $this->resourceClashes($movements),
            $this->turnarounds($movements),
            $this->capacity($movements),
            $this->windowPolicy($movements, $eventId),
            $this->matchTiming($movements),
            $this->stayWindows($movements, $eventId),
            $this->driverDuty($movements),
            $this->unassigned($movements),
        );

        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($conflicts, fn ($a, $b) => [$rank[$a['sev']], $a['sort']] <=> [$rank[$b['sev']], $b['sort']]);

        return array_map(function (array $c) {
            unset($c['sort']);

            return $c;
        }, $conflicts);
    }

    /* ------------------------------------------------------------------ */

    /** Windows that are missing, backwards, or absurdly long. */
    private function scheduleIntegrity(Collection $movements): array
    {
        $out = [];

        foreach ($movements as $m) {
            if (!$m->window_start || !$m->window_end) {
                $out[] = $this->make('SCH', $m->id, 'medium', 'Missing Time Window',
                    sprintf('%s has no %s time, so it cannot be sequenced or checked against flights and kick-offs.',
                        $this->label($m),
                        !$m->window_start && !$m->window_end ? 'start or end' : (!$m->window_start ? 'start' : 'end')),
                    [$m], $m->window_start ?? $m->window_end,
                    'Set both a start and end time on the movement.');
                continue;
            }

            if ($m->window_end->lte($m->window_start)) {
                $out[] = $this->make('SCH', $m->id, 'high', 'Invalid Time Window',
                    sprintf('%s ends at %s, which is before or equal to its %s start.',
                        $this->label($m), $m->window_end->format('D H:i'), $m->window_start->format('H:i')),
                    [$m], $m->window_start,
                    'Correct the end time so the movement runs forwards.');
                continue;
            }

            if ($m->window_start->diffInMinutes($m->window_end) > 720) {
                $out[] = $this->make('SCH', $m->id, 'low', 'Unusually Long Window',
                    sprintf('%s is scheduled to run for %s hours — likely a data entry slip.',
                        $this->label($m), round($m->window_start->diffInMinutes($m->window_end) / 60, 1)),
                    [$m], $m->window_start,
                    'Confirm the end time is on the intended day.');
            }
        }

        return $out;
    }

    /** Same vehicle / driver / supervisor / team in two movements at once. */
    private function resourceClashes(Collection $movements): array
    {
        $out = [];

        $checks = [
            ['vehicle_id', 'high', 'Vehicle Double-Booked', fn ($m) => $m->vehicle?->code ?? 'Vehicle #' . $m->vehicle_id, 'Reassign one movement to another vehicle.'],
            ['driver_id', 'high', 'Driver Double-Booked', fn ($m) => $m->driver?->name ?? 'Driver #' . $m->driver_id, 'Reassign one movement to another driver.'],
            ['team_id', 'high', 'Team In Two Places', fn ($m) => $m->team?->code ?? 'Team #' . $m->team_id, 'The same delegation cannot travel twice at once — merge or re-time.'],
            ['field_supervisor_id', 'medium', 'Supervisor Double-Booked', fn ($m) => $m->fieldSupervisor?->name ?? 'Supervisor #' . $m->field_supervisor_id, 'Assign a second supervisor or stagger the movements.'],
        ];

        foreach ($checks as [$key, $sev, $type, $nameFn, $hint]) {
            $groups = $movements->filter(fn ($m) => $m->$key && $m->window_start && $m->window_end)->groupBy($key);

            foreach ($groups as $group) {
                $list = $group->sortBy('window_start')->values();
                for ($i = 0; $i < $list->count(); $i++) {
                    for ($j = $i + 1; $j < $list->count(); $j++) {
                        $a = $list[$i];
                        $b = $list[$j];
                        if ($b->window_start->gte($a->window_end)) {
                            break; // sorted - nothing further overlaps
                        }
                        $overlap = $a->window_end->min($b->window_end)->diffInMinutes($b->window_start);
                        $out[] = $this->make($type[0] . 'DB', $a->id . '-' . $b->id, $sev, $type,
                            sprintf('%s is booked on %s (%s–%s) and %s (%s–%s) — %d minutes overlap.',
                                $nameFn($a), $this->label($a),
                                $a->window_start->format('D H:i'), $a->window_end->format('H:i'),
                                $this->label($b),
                                $b->window_start->format('D H:i'), $b->window_end->format('H:i'),
                                $overlap),
                            [$a, $b], $a->window_start, $hint);
                    }
                }
            }
        }

        return $out;
    }

    /** Back-to-back jobs with no realistic gap in between. */
    private function turnarounds(Collection $movements): array
    {
        $out = [];

        foreach ([['vehicle_id', fn ($m) => $m->vehicle?->code ?? 'Vehicle'], ['driver_id', fn ($m) => $m->driver?->name ?? 'Driver']] as [$key, $nameFn]) {
            $groups = $movements->filter(fn ($m) => $m->$key && $m->window_start && $m->window_end)->groupBy($key);

            foreach ($groups as $group) {
                $list = $group->sortBy('window_start')->values();
                for ($i = 0; $i < $list->count() - 1; $i++) {
                    $a = $list[$i];
                    $b = $list[$i + 1];
                    if ($b->window_start->lt($a->window_end)) {
                        continue; // already reported as a double-booking
                    }
                    $gap = $a->window_end->diffInMinutes($b->window_start);
                    if ($gap < self::TURNAROUND_MINUTES) {
                        $out[] = $this->make('TRN', $a->id . '-' . $b->id, 'medium', 'Tight Turnaround',
                            sprintf('%s has only %d minute%s between %s and %s — no margin for traffic or loading.',
                                $nameFn($a), $gap, $gap === 1 ? '' : 's', $this->label($a), $this->label($b)),
                            [$a, $b], $a->window_end,
                            sprintf('Allow at least %d minutes, or split across two resources.', self::TURNAROUND_MINUTES));
                    }
                }
            }
        }

        return $out;
    }

    /** More passengers than the assigned vehicle can carry. */
    private function capacity(Collection $movements): array
    {
        $out = [];

        foreach ($movements as $m) {
            $pax = $m->passengers ?: $m->flight?->party_size_total;
            $capacity = $m->vehicle?->capacity;

            if ($pax && $capacity && $pax > $capacity) {
                $out[] = $this->make('CAP', $m->id, 'high', 'Capacity Exceeded',
                    sprintf('%s carries %d passengers but %s seats only %d — %d people have no seat.',
                        $this->label($m), $pax, $m->vehicle->code ?? 'the assigned vehicle', $capacity, $pax - $capacity),
                    [$m], $m->window_start,
                    'Upgrade the vehicle or add a second one to the movement.');
            }

            if ($m->vehicle && $m->vehicle->status === 'maintenance') {
                $out[] = $this->make('VST', $m->id, 'medium', 'Vehicle Out Of Service',
                    sprintf('%s is assigned to %s, which is currently flagged as in maintenance.',
                        $m->vehicle->code ?? 'A vehicle', $this->label($m)),
                    [$m], $m->window_start,
                    'Swap in an available vehicle or clear the maintenance flag.');
            }
        }

        return $out;
    }

    /**
     * Compares each window against the offset configured under
     * Setups > Movement Time Offset Settings, rather than against a hardcoded
     * rule. A pickup that begins before the aircraft lands is intentional when
     * the offset is negative — what matters is whether the window still
     * matches the policy it was generated from, since settings can be edited
     * (or a window hand-corrected) long after job generation ran.
     */
    private function windowPolicy(Collection $movements, int $eventId): array
    {
        $out = [];

        foreach ($movements as $m) {
            $reference = $this->referenceTime($m);
            if (!$reference || !$m->window_start) {
                continue;
            }

            [$offset, $source] = $this->resolveOffset($m, $eventId);
            $expected = $reference->copy()->addMinutes($offset);
            $drift = $expected->diffInMinutes($m->window_start, false); // positive = starts later than policy

            if (abs($drift) <= self::OFFSET_TOLERANCE_MINUTES) {
                continue;
            }

            $out[] = $this->make('OFS', $m->id, abs($drift) > 180 ? 'medium' : 'low', 'Window Out Of Sync With Settings',
                sprintf('%s starts at %s, %d minutes %s the %s %s configures (%s, %s %s).',
                    $this->label($m), $m->window_start->format('D H:i'), abs($drift),
                    $drift > 0 ? 'later than' : 'earlier than', $source,
                    $this->describeOffset($offset, $this->anchorLabel($m)),
                    $expected->format('D H:i'),
                    $this->anchorLabel($m), $reference->format('H:i')),
                [$m], $m->window_start,
                'Re-run window generation for this movement type, or correct the offset in Settings.');
        }

        return $out;
    }

    /** The real-world event a movement's window hangs off: flight, kick-off, or training start. */
    private function referenceTime(Movement $m): ?Carbon
    {
        return match ($m->kind) {
            'arrival', 'departure', 'transfer' => $m->flight?->estimated_at ?? $m->flight?->scheduled_at,
            'match' => $m->match?->kick_off ? Carbon::parse($m->match->kick_off) : null,
            default => null,
        };
    }

    private function anchorLabel(Movement $m): string
    {
        return match ($m->kind) {
            'arrival' => $m->flight?->flight_number ? $m->flight->flight_number . ' landing' : 'landing',
            'departure' => $m->flight?->flight_number ? $m->flight->flight_number . ' departure' : 'departure',
            'match' => 'kick-off',
            default => 'the reference time',
        };
    }

    /**
     * Mirrors JobGenerationService: a checkpoint-specific override on the
     * template's first checkpoint wins, otherwise the event → global
     * movement-type cascade.
     *
     * @return array{0: int, 1: string} offset in minutes, and where it came from
     */
    private function resolveOffset(Movement $m, int $eventId): array
    {
        $firstCheckpoint = $m->checkpointTemplate?->checkpoints->first();

        if ($firstCheckpoint) {
            $override = $this->settings->getCheckpointOffset($firstCheckpoint->id, (string) $m->kind, $eventId);
            if ($override !== null) {
                return [$override, sprintf('"%s" checkpoint override', $firstCheckpoint->name)];
            }
        }

        return [
            $this->settings->getMovementOffset((string) $m->kind, $eventId),
            sprintf('%s movement default', $m->kind),
        ];
    }

    /** Renders a configured offset in minutes as plain English, e.g. "3h before landing". */
    private function describeOffset(int $minutes, string $anchor): string
    {
        if ($minutes === 0) {
            return 'exactly at ' . $anchor;
        }

        $abs = abs($minutes);
        $span = $abs % 60 === 0 ? intdiv($abs, 60) . 'h' : $abs . 'm';

        return sprintf('%s %s %s', $span, $minutes < 0 ? 'before' : 'after', $anchor);
    }

    /** Stadium transfers that set off too late for the team to be on site in time. */
    private function matchTiming(Collection $movements): array
    {
        $out = [];

        foreach ($movements as $m) {
            $kickOff = $m->match?->kick_off ? Carbon::parse($m->match->kick_off) : null;
            if (!$kickOff || !$m->window_start) {
                continue;
            }

            $matchLabel = $m->match->match_number ?: 'the match';
            $deadline = $kickOff->copy()->subMinutes(self::KICKOFF_LEAD_MINUTES);

            if ($m->window_start->gte($kickOff)) {
                $out[] = $this->make('MCH', $m->id, 'high', 'Sets Off After Kick-Off',
                    sprintf('%s does not start until %s — %s kicked off at %s, %d minutes earlier.',
                        $this->label($m), $m->window_start->format('D H:i'), $matchLabel,
                        $kickOff->format('H:i'), $kickOff->diffInMinutes($m->window_start)),
                    [$m], $m->window_start,
                    'Re-time the transfer to reach the venue before the teams are due.');
            } elseif ($m->window_start->gt($deadline)) {
                $out[] = $this->make('MCH', $m->id, 'high', 'Late For Kick-Off',
                    sprintf('%s sets off for %s at %s, only %d minutes before %s kicks off — no travel margin.',
                        $this->label($m), $m->match->venue?->name ?? 'the venue',
                        $m->window_start->format('D H:i'), $m->window_start->diffInMinutes($kickOff), $matchLabel),
                    [$m], $m->window_start,
                    sprintf('Teams are normally on site %d minutes before kick-off.', self::KICKOFF_LEAD_MINUTES));
            }
        }

        return $out;
    }

    /** Movements scheduled while the team is not yet (or no longer) in the hotel. */
    private function stayWindows(Collection $movements, int $eventId): array
    {
        $teamIds = $movements->pluck('team_id')->filter()->unique();
        if ($teamIds->isEmpty()) {
            return [];
        }

        $stays = TeamStay::where('event_id', $eventId)
            ->whereIn('team_id', $teamIds)
            ->get()
            ->groupBy('team_id');

        $out = [];

        foreach ($movements as $m) {
            $stay = $stays->get($m->team_id)?->first();
            if (!$stay || !$m->window_start || $m->kind === 'arrival' || $m->kind === 'departure') {
                continue;
            }

            $day = $m->window_start->copy()->startOfDay();

            if ($stay->check_in && $day->lt(Carbon::parse($stay->check_in)->startOfDay())) {
                $out[] = $this->make('STY', $m->id, 'medium', 'Before Hotel Check-In',
                    sprintf('%s runs on %s, before %s checks into %s on %s.',
                        $this->label($m), $day->format('D j M'), $m->team?->code ?? 'the team',
                        $stay->hotel_name ?: 'their hotel', Carbon::parse($stay->check_in)->format('D j M')),
                    [$m], $m->window_start,
                    'Move the movement inside the accommodation window, or extend the stay.');
            }

            if ($stay->check_out && $day->gt(Carbon::parse($stay->check_out)->startOfDay())) {
                $out[] = $this->make('STY', $m->id, 'medium', 'After Hotel Check-Out',
                    sprintf('%s runs on %s, after %s checks out of %s on %s.',
                        $this->label($m), $day->format('D j M'), $m->team?->code ?? 'the team',
                        $stay->hotel_name ?: 'their hotel', Carbon::parse($stay->check_out)->format('D j M')),
                    [$m], $m->window_start,
                    'Re-time the movement or extend the accommodation.');
            }
        }

        return $out;
    }

    /** Drivers whose day stretches beyond a safe shift. */
    private function driverDuty(Collection $movements): array
    {
        $out = [];

        $byDriverDay = $movements
            ->filter(fn ($m) => $m->driver_id && $m->window_start && $m->window_end)
            ->groupBy(fn ($m) => $m->driver_id . '|' . $m->window_start->toDateString());

        foreach ($byDriverDay as $group) {
            if ($group->count() < 2) {
                continue;
            }

            $first = $group->min('window_start');
            $last = $group->max('window_end');
            $spanHours = $first->diffInMinutes($last) / 60;

            if ($spanHours > self::DRIVER_SPAN_HOURS) {
                $sorted = $group->sortBy('window_start')->values();
                $out[] = $this->make('DTY', $sorted->first()->driver_id . '-' . $first->toDateString(), 'medium', 'Driver Shift Too Long',
                    sprintf('%s is on duty from %s to %s on %s — a %.1f hour span across %d movements.',
                        $sorted->first()->driver?->name ?? 'A driver', $first->format('H:i'), $last->format('H:i'),
                        $first->format('D j M'), $spanHours, $group->count()),
                    $sorted->all(), $first,
                    sprintf('Split the day across two drivers — the limit is %d hours.', self::DRIVER_SPAN_HOURS));
            }
        }

        return $out;
    }

    /** Movements still missing a vehicle, driver or supervisor. */
    private function unassigned(Collection $movements): array
    {
        $out = [];

        foreach ($movements as $m) {
            $missing = [];
            if (!$m->vehicle_id) {
                $missing[] = 'vehicle';
            }
            if (!$m->driver_id) {
                $missing[] = 'driver';
            }

            if ($missing === []) {
                continue;
            }

            $imminent = $m->window_start && $m->window_start->isBetween(now(), now()->addHours(48));

            $out[] = $this->make('ASG', $m->id, $imminent ? 'high' : 'low', 'Unassigned Resource',
                sprintf('%s has no %s assigned%s.',
                    $this->label($m), implode(' and no ', $missing),
                    $imminent ? ' and departs within 48 hours' : ''),
                [$m], $m->window_start,
                'Assign from the available pool, or let job generation auto-assign.');
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */

    /**
     * @param array<int, Movement> $affected
     */
    private function make(string $prefix, string|int $key, string $sev, string $type, string $text, array $affected, ?Carbon $when, string $hint): array
    {
        $first = $affected[0] ?? null;

        return [
            'id' => $prefix . '-' . $key,
            'sev' => $sev,
            'type' => $type,
            'text' => $text,
            'hint' => $hint,
            'affects' => array_values(array_unique(array_map(fn ($m) => $this->label($m), $affected))),
            'movement_ids' => array_values(array_unique(array_map(fn ($m) => $m->id, $affected))),
            'plan' => $first?->plan?->name ?? $first?->plan?->code,
            'plan_id' => $first?->plan_id,
            'when' => $when?->format('D j M H:i'),
            'sort' => $when?->timestamp ?? PHP_INT_MAX,
        ];
    }

    private function label(Movement $m): string
    {
        return $m->code ?: 'MV-' . $m->id;
    }
}
