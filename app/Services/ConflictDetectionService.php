<?php

namespace App\Services;

use App\Models\ConflictAcceptance;
use App\Models\Driver;
use App\Models\Movement;
use App\Models\TeamStay;
use App\Models\User;
use App\Models\Vehicle;
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

    /** Minimum rest between a driver's working days. */
    private const DRIVER_REST_HOURS = 11;

    /** A gap this long between timed checkpoints is waiting (e.g. the match), not work. */
    private const STANDBY_GAP_MINUTES = 60;

    public function __construct(private SettingsService $settings, private JobGenerationService $jobs)
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

        $movements = $this->eventMovements($eventId);

        if ($movements->isEmpty()) {
            return [];
        }

        $spans = $this->occupations($movements);

        $conflicts = array_merge(
            $this->scheduleIntegrity($movements),
            $this->resourceClashes($movements, $spans),
            $this->turnarounds($movements, $spans),
            $this->crewClashes($movements, $spans),
            $this->capacity($movements),
            $this->windowPolicy($movements, $eventId),
            $this->matchTiming($movements),
            $this->stayWindows($movements, $eventId),
            $this->driverDuty($movements, $spans),
            $this->unassigned($movements),
        );

        $accepted = ConflictAcceptance::with('user:id,name')
            ->where('event_id', $eventId)
            ->get()
            ->keyBy('conflict_id');

        foreach ($conflicts as &$c) {
            $a = $accepted->get($c['id']);
            $c['accepted'] = $a ? [
                'reason' => $a->reason,
                'by' => $a->user?->name ?? 'Unknown',
                'at' => $a->updated_at?->format('D j M H:i'),
            ] : null;
        }
        unset($c);

        $rank = ['high' => 0, 'medium' => 1, 'low' => 2];
        usort($conflicts, fn ($a, $b) => [(bool) $a['accepted'], $rank[$a['sev']], $a['sort']] <=> [(bool) $b['accepted'], $rank[$b['sev']], $b['sort']]);

        return array_map(function (array $c) {
            unset($c['sort']);

            return $c;
        }, $conflicts);
    }

    /**
     * Who could take this movement without clashing: every driver, supervisor
     * and vehicle, each marked free or with the reason it isn't — judged by the
     * same rules the conflict list uses.
     */
    public function crewOptions(Movement $movement): array
    {
        $movements = $this->eventMovements($movement->event_id);
        $target = $movements->firstWhere('id', $movement->id);
        if (!$target) {
            return ['movement' => null, 'drivers' => [], 'supervisors' => [], 'vehicles' => []];
        }

        $spans = $this->occupations($movements);
        $span = $this->wholeSpan($target, $spans);
        $active = $spans[$target->id]['active'] ?? [];
        $others = $movements->where('id', '!=', $target->id);
        $pax = $target->passengers ?: $target->flight?->party_size_total;

        // First reason the resource can't take the target, or null when free.
        $clash = function (string $key, int $id, string $role) use ($others, $spans, $span, $active, $target): ?string {
            if (!$span) {
                return null;
            }

            foreach ($others->where($key, $id) as $m) {
                $otherSpan = $this->wholeSpan($m, $spans);
                if (!$otherSpan) {
                    continue;
                }

                $label = $this->label($m) . ' ' . $otherSpan[0]->format('D H:i') . '–' . $otherSpan[1]->format('H:i');
                $spansOverlap = $otherSpan[0]->lt($span[1]) && $span[0]->lt($otherSpan[1]);
                $otherActive = $spans[$m->id]['active'] ?? [];

                if ($role === 'vehicle') {
                    if ($spansOverlap) {
                        return "Busy on {$label}";
                    }
                } elseif ($this->overlapMinutes($active, $otherActive) > 0) {
                    return "Working on {$label}";
                } elseif ($role === 'supervisor' && $spansOverlap && !$this->sameComplex($target, $m)) {
                    return "With {$label}";
                }

                $near = $otherSpan[0]->lt($span[1]->copy()->addMinutes(self::TURNAROUND_MINUTES))
                    && $span[0]->lt($otherSpan[1]->copy()->addMinutes(self::TURNAROUND_MINUTES));
                $gap = $role === 'vehicle'
                    ? $this->smallestGap([$span], [$otherSpan])
                    : $this->smallestGap($active, $otherActive);
                if ($near && $gap !== null && $gap < self::TURNAROUND_MINUTES) {
                    return "Only {$gap} min from {$label}";
                }

                if ($role === 'driver' && !$spansOverlap && $span[0]->toDateString() !== $otherSpan[0]->toDateString()) {
                    $rest = min(
                        abs($otherSpan[1]->diffInMinutes($span[0], false)),
                        abs($span[1]->diffInMinutes($otherSpan[0], false)),
                    ) / 60;
                    if ($rest < self::DRIVER_REST_HOURS) {
                        return sprintf('Only %.1fh rest around %s', $rest, $label);
                    }
                }
            }

            return null;
        };

        $option = fn ($id, string $label, ?string $reason, array $extra = []) => [
            'id' => $id, 'label' => $label, 'free' => $reason === null, 'reason' => $reason,
        ] + $extra;
        $freeFirst = fn (Collection $list) => $list->sortBy(fn ($o) => [!$o['free'], $o['label']])->values()->all();

        return [
            'movement' => [
                'id' => $target->id,
                'code' => $this->label($target),
                'pax' => $pax,
                'vehicle_id' => $target->vehicle_id,
                'driver_id' => $target->driver_id,
                'field_supervisor_id' => $target->field_supervisor_id,
                'span' => $span ? [$span[0]->format('Y-m-d H:i'), $span[1]->format('Y-m-d H:i')] : null,
                'active' => array_map(fn ($s) => [$s[0]->format('Y-m-d H:i'), $s[1]->format('Y-m-d H:i')], $active),
            ],
            'drivers' => $freeFirst(Driver::orderBy('name')->get(['id', 'name', 'status'])->map(fn (Driver $d) => $option(
                $d->id, $d->name,
                in_array($d->status, ['off', 'rest'], true) ? 'Marked ' . ($d->status === 'off' ? 'off' : 'rest day') : $clash('driver_id', $d->id, 'driver'),
            ))),
            'supervisors' => $freeFirst(User::permission('jobs.view')->orderBy('name')->get(['id', 'name'])
                ->when($target->fieldSupervisor, fn ($list) => $list->push($target->fieldSupervisor)->unique('id'))
                ->map(fn (User $u) => $option(
                    $u->id, $u->name, $clash('field_supervisor_id', $u->id, 'supervisor'),
                ))),
            'vehicles' => $freeFirst(Vehicle::orderBy('code')->get(['id', 'code', 'plate_number', 'vehicle_type', 'capacity', 'status'])->map(fn (Vehicle $v) => $option(
                $v->id,
                ($v->code ?? $v->plate_number ?? "#{$v->id}") . ($v->capacity ? " · {$v->capacity} seats" : ''),
                match (true) {
                    $v->status === 'maintenance' => 'In maintenance',
                    $pax && $v->capacity && $v->capacity < $pax => "Seats {$v->capacity}, needs {$pax}",
                    default => $clash('vehicle_id', $v->id, 'vehicle'),
                },
            ))),
        ];
    }

    /** Every live movement of an event with what the checks need. */
    private function eventMovements(int $eventId): Collection
    {
        return Movement::with([
                'plan:id,code,name,date',
                'team:id,code,team_name',
                'vehicle:id,code,capacity,status,vehicle_type',
                'driver:id,name,status',
                'fieldSupervisor:id,name',
                'flight:id,team_id,direction,flight_number,scheduled_at,estimated_at,party_size_total',
                'match:id,match_number,kick_off,venue_id',
                'match.venue:id,name',
                'checkpointTemplate.checkpoints:id,name',
                'job:id,movement_id',
                'job.checkpoints:id,job_id,scheduled_at',
            ])
            ->where('event_id', $eventId)
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->orderBy('window_start')
            ->get();
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

    /**
     * Same vehicle or team in two movements at once. A coach waits with its
     * team (e.g. through the match), so it is taken for the job's whole span.
     */
    private function resourceClashes(Collection $movements, array $spans): array
    {
        $out = [];

        $checks = [
            ['vehicle_id', 'high', 'Vehicle Double-Booked', fn ($m) => $m->vehicle?->code ?? 'Vehicle #' . $m->vehicle_id, 'Reassign one movement to another vehicle.', fn ($m) => $this->wholeSpan($m, $spans)],
            ['team_id', 'high', 'Team In Two Places', fn ($m) => $m->team?->code ?? 'Team #' . $m->team_id, 'The same delegation cannot travel twice at once — merge or re-time.', fn ($m) => $this->window($m)],
        ];

        foreach ($checks as [$key, $sev, $type, $nameFn, $hint, $interval]) {
            foreach ($this->timedGroups($movements, $key, $interval) as $list) {
                for ($i = 0; $i < count($list); $i++) {
                    for ($j = $i + 1; $j < count($list); $j++) {
                        [$a, [$aStart, $aEnd]] = $list[$i];
                        [$b, [$bStart, $bEnd]] = $list[$j];
                        if ($bStart->gte($aEnd)) {
                            break; // sorted - nothing further overlaps
                        }
                        $overlap = (int) $aEnd->min($bEnd)->diffInMinutes($bStart, true);
                        $out[] = $this->make($type[0] . 'DB', $a->id . '-' . $b->id, $sev, $type,
                            sprintf('%s is booked on %s (%s–%s) and %s (%s–%s) — %d minutes overlap.',
                                $nameFn($a), $this->label($a), $aStart->format('D H:i'), $aEnd->format('H:i'),
                                $this->label($b), $bStart->format('D H:i'), $bEnd->format('H:i'), $overlap),
                            [$a, $b], $aStart, $hint);
                    }
                }
            }
        }

        return $out;
    }

    /** Back-to-back vehicle jobs with no realistic gap in between. */
    private function turnarounds(Collection $movements, array $spans): array
    {
        $out = [];

        foreach ($this->timedGroups($movements, 'vehicle_id', fn ($m) => $this->wholeSpan($m, $spans)) as $list) {
            for ($i = 0; $i < count($list) - 1; $i++) {
                [$a, [, $aEnd]] = $list[$i];
                [$b, [$bStart]] = $list[$i + 1];
                if ($bStart->lt($aEnd)) {
                    continue; // already reported as a double-booking
                }
                $gap = (int) $aEnd->diffInMinutes($bStart, true);
                if ($gap < self::TURNAROUND_MINUTES) {
                    $out[] = $this->make('TRN', $a->id . '-' . $b->id, 'medium', 'Tight Turnaround',
                        sprintf('%s has only %d minute%s between %s and %s — no margin for traffic or loading.',
                            $a->vehicle?->code ?? 'Vehicle', $gap, $gap === 1 ? '' : 's', $this->label($a), $this->label($b)),
                        [$a, $b], $aEnd,
                        sprintf('Allow at least %d minutes, or split across two resources.', self::TURNAROUND_MINUTES));
                }
            }
        }

        return $out;
    }

    /**
     * Drivers and supervisors, judged on when they actually work rather than
     * the pickup window. A driver may cover another job while one team is
     * waiting (split duty); a supervisor may do so only within the same venue
     * complex, since they stay with the team.
     */
    private function crewClashes(Collection $movements, array $spans): array
    {
        $out = [];

        $roles = [
            'driver_id' => ['D', 'Driver', fn ($m) => $m->driver?->name ?? 'Driver #' . $m->driver_id],
            'field_supervisor_id' => ['S', 'Supervisor', fn ($m) => $m->fieldSupervisor?->name ?? 'Supervisor #' . $m->field_supervisor_id],
        ];

        foreach ($roles as $key => [$prefix, $role, $nameFn]) {
            foreach ($this->timedGroups($movements, $key, fn ($m) => $this->wholeSpan($m, $spans)) as $list) {
                for ($i = 0; $i < count($list); $i++) {
                    for ($j = $i + 1; $j < count($list); $j++) {
                        [$a, [$aStart, $aEnd]] = $list[$i];
                        [$b, [$bStart]] = $list[$j];
                        if ($bStart->gte($aEnd->copy()->addMinutes(self::TURNAROUND_MINUTES))) {
                            break;
                        }

                        $pairKey = $a->id . '-' . $b->id;
                        $who = $nameFn($a);
                        $activeA = $spans[$a->id]['active'] ?? [$this->window($a)];
                        $activeB = $spans[$b->id]['active'] ?? [$this->window($b)];
                        $overlap = $this->overlapMinutes($activeA, $activeB);
                        $gap = $this->smallestGap($activeA, $activeB);

                        if ($overlap > 0) {
                            $out[] = $this->make($prefix . 'DB', $pairKey, 'high', "{$role} Double-Booked",
                                sprintf('%s is working on %s (%s) and %s (%s) at the same time — %d minutes overlap.',
                                    $who, $this->label($a), $this->describeActive($activeA),
                                    $this->label($b), $this->describeActive($activeB), $overlap),
                                [$a, $b], $aStart, "Reassign one movement to another " . strtolower($role) . '.');
                            continue;
                        }

                        $spansOverlap = $bStart->lt($aEnd);

                        if ($spansOverlap && $prefix === 'S' && !$this->sameComplex($a, $b)) {
                            $out[] = $this->make('SDB', $pairKey, 'high', 'Supervisor Double-Booked',
                                sprintf('%s stays with %s until %s but is due on %s at %s, at a different venue.',
                                    $who, $this->label($a), $aEnd->format('H:i'), $this->label($b), $bStart->format('D H:i')),
                                [$a, $b], $aStart, 'A supervisor can only cover two teams at the same venue complex — assign a second supervisor.');
                            continue;
                        }

                        if ($gap !== null && $gap < self::TURNAROUND_MINUTES) {
                            $out[] = $this->make('TRN', $prefix . $pairKey, 'medium', 'Tight Turnaround',
                                sprintf('%s has only %d minute%s between %s and %s — no margin for traffic or getting between locations.',
                                    $who, $gap, $gap === 1 ? '' : 's', $this->label($a), $this->label($b)),
                                [$a, $b], $aStart,
                                sprintf('Allow at least %d minutes, or reassign one side.', self::TURNAROUND_MINUTES));
                            continue;
                        }

                        if ($spansOverlap && $prefix === 'D') {
                            $out[] = $this->make('SPL', $pairKey, 'low', 'Split Duty',
                                sprintf('%s drives %s while %s is waiting (%s–%s). Allowed, but %s\'s vehicle is left unattended.',
                                    $who, $this->label($b), $this->label($a), $aStart->format('H:i'), $aEnd->format('H:i'), $this->label($a)),
                                [$a, $b], $bStart, sprintf('Make sure %s can get back for %s\'s return.', $who, $this->label($a)));
                        }
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

    /** Drivers whose day stretches beyond a safe shift, or who start again without enough rest. */
    private function driverDuty(Collection $movements, array $spans): array
    {
        $out = [];

        $byDriver = $movements
            ->filter(fn ($m) => $m->driver_id && $this->wholeSpan($m, $spans))
            ->groupBy('driver_id');

        foreach ($byDriver as $driverMovements) {
            $name = $driverMovements->first()->driver?->name ?? 'A driver';

            // One duty day per date, waiting time included: first start to last finish.
            $days = $driverMovements
                ->groupBy(fn ($m) => $this->wholeSpan($m, $spans)[0]->toDateString())
                ->sortKeys()
                ->map(fn ($group) => [
                    'movements' => $group->sortBy(fn ($m) => $this->wholeSpan($m, $spans)[0]->timestamp)->values(),
                    'start' => $group->map(fn ($m) => $this->wholeSpan($m, $spans)[0])->min(),
                    'end' => $group->map(fn ($m) => $this->wholeSpan($m, $spans)[1])->max(),
                ])
                ->values();

            foreach ($days as $i => $day) {
                $hours = $day['start']->diffInMinutes($day['end'], true) / 60;

                if ($day['movements']->count() > 1 && $hours > self::DRIVER_SPAN_HOURS) {
                    $out[] = $this->make('DTY', $day['movements']->first()->driver_id . '-' . $day['start']->toDateString(), 'medium', 'Driver Shift Too Long',
                        sprintf('%s is on duty from %s to %s on %s — a %.1f hour span across %d movements.',
                            $name, $day['start']->format('H:i'), $day['end']->format('H:i'),
                            $day['start']->format('D j M'), $hours, $day['movements']->count()),
                        $day['movements']->all(), $day['start'],
                        sprintf('Split the day across two drivers — the limit is %d hours.', self::DRIVER_SPAN_HOURS));
                }

                $next = $days[$i + 1] ?? null;
                if (!$next || $next['start']->lte($day['end'])) {
                    continue;
                }

                $rest = $day['end']->diffInMinutes($next['start'], true) / 60;
                if ($rest < self::DRIVER_REST_HOURS) {
                    $out[] = $this->make('RST', $day['movements']->first()->driver_id . '-' . $next['start']->toDateString(), 'medium', 'Insufficient Rest',
                        sprintf('%s finishes at %s on %s and starts again at %s on %s — only %.1f hours of rest.',
                            $name, $day['end']->format('H:i'), $day['end']->format('D j M'),
                            $next['start']->format('H:i'), $next['start']->format('D j M'), $rest),
                        [$day['movements']->last(), $next['movements']->first()], $next['start'],
                        sprintf('Drivers need at least %d hours between shifts — reassign the early job.', self::DRIVER_REST_HOURS));
                }
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
     * When each movement really occupies its crew: every checkpoint time (the
     * job's, or the pre-job estimate) plus the movement window, split into
     * working stretches wherever checkpoints are far apart.
     *
     * @return array<int, array{start: Carbon, end: Carbon, active: array<int, array{0: Carbon, 1: Carbon}>}>
     */
    private function occupations(Collection $movements): array
    {
        $out = [];

        foreach ($movements as $m) {
            $times = $m->job
                ? $m->job->checkpoints->pluck('scheduled_at')->filter()
                : collect($this->jobs->estimateCheckpointSchedule($m))->filter()->map(fn ($t) => Carbon::parse($t));

            $segments = [];
            if ($window = $this->window($m)) {
                $segments[] = [$window[0]->copy(), $window[1]->copy()];
            }

            $run = null;
            foreach ($times->sort()->values() as $t) {
                if ($run && $run[1]->diffInMinutes($t, true) < self::STANDBY_GAP_MINUTES) {
                    $run[1] = $t->copy();
                    continue;
                }
                if ($run) {
                    $segments[] = $run;
                }
                $run = [$t->copy(), $t->copy()];
            }
            if ($run) {
                $segments[] = $run;
            }

            // A lone marker such as the final whistle is a moment, not work.
            $segments = array_values(array_filter($segments, fn ($s) => $s[1]->gt($s[0])));

            // The span runs from the first to the last known time, waiting included.
            $points = $times->values()->all();
            if ($window) {
                array_push($points, $window[0], $window[1]);
            }
            if ($points === []) {
                continue;
            }

            usort($segments, fn ($a, $b) => $a[0] <=> $b[0]);
            $merged = $segments ? [array_shift($segments)] : [];
            foreach ($segments as $s) {
                $last = &$merged[count($merged) - 1];
                if ($s[0]->lte($last[1])) {
                    $last[1] = $last[1]->max($s[1]);
                } else {
                    $merged[] = $s;
                }
                unset($last);
            }

            $out[$m->id] = [
                'start' => collect($points)->min(),
                'end' => collect($points)->max(),
                'active' => $merged,
            ];
        }

        return $out;
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    private function window(Movement $m): ?array
    {
        return $m->window_start && $m->window_end && $m->window_end->gt($m->window_start)
            ? [$m->window_start, $m->window_end]
            : null;
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    private function wholeSpan(Movement $m, array $spans): ?array
    {
        return isset($spans[$m->id]) ? [$spans[$m->id]['start'], $spans[$m->id]['end']] : $this->window($m);
    }

    /**
     * Movements sharing a resource, each with its interval, sorted by start.
     *
     * @return array<int, array<int, array{0: Movement, 1: array{0: Carbon, 1: Carbon}}>>
     */
    private function timedGroups(Collection $movements, string $key, callable $interval): array
    {
        return $movements
            ->filter(fn ($m) => $m->$key && $interval($m))
            ->groupBy($key)
            ->map(fn ($group) => $group
                ->map(fn ($m) => [$m, $interval($m)])
                ->sortBy(fn ($pair) => $pair[1][0]->timestamp)
                ->values()
                ->all())
            ->values()
            ->all();
    }

    private function overlapMinutes(array $a, array $b): int
    {
        $total = 0;
        foreach ($a as [$aStart, $aEnd]) {
            foreach ($b as [$bStart, $bEnd]) {
                $start = $aStart->max($bStart);
                $end = $aEnd->min($bEnd);
                if ($end->gt($start)) {
                    $total += (int) $start->diffInMinutes($end, true);
                }
            }
        }

        return $total;
    }

    /** Shortest break between any working stretch of one movement and any of the other. */
    private function smallestGap(array $a, array $b): ?int
    {
        $gaps = [];
        foreach ($a as [$aStart, $aEnd]) {
            foreach ($b as [$bStart, $bEnd]) {
                if ($aEnd->lte($bStart)) {
                    $gaps[] = (int) $aEnd->diffInMinutes($bStart, true);
                } elseif ($bEnd->lte($aStart)) {
                    $gaps[] = (int) $bEnd->diffInMinutes($aStart, true);
                }
            }
        }

        return $gaps ? min($gaps) : null;
    }

    private function describeActive(array $segments): string
    {
        return implode(', ', array_map(fn ($s) => $s[0]->format('D H:i') . '–' . $s[1]->format('H:i'), $segments));
    }

    /** "ASPIRE ZONE (PITCH 1)" and "ASPIRE ZONE (PITCH 7)" are the same complex. */
    private function sameComplex(Movement $a, Movement $b): bool
    {
        $complex = fn (Movement $m) => ($name = $m->match?->venue?->name)
            ? strtoupper(trim(preg_replace('/\s*\(.*\)\s*$/', '', $name)))
            : null;

        return $complex($a) !== null && $complex($a) === $complex($b);
    }

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
