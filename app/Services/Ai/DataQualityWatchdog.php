<?php

namespace App\Services\Ai;

use App\Models\Driver;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\TeamFlight;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\OperationsQueryService;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Daleel's data-quality watchdog: data that will cause trouble on the ground. Read-only and deterministic;
 * limits are the data checks in Settings → Conflict rules. Flight staleness follows `flights:sync`, which
 * refreshes flights from 6 h ago to 12 h ahead: every run within 3 h, every 3 h further out, and sets
 * `flight_synced_at` only when a refresh succeeds.
 */
class DataQualityWatchdog
{
    public const ITEMS = 10;

    private const TITLES = [
        'stale_flights' => 'Flight data not refreshed',
        'unconfirmed_flights' => 'Flights with no landing or departure recorded',
        'no_job' => 'Movements with no job yet',
        'invalid_crew' => 'Crew that no longer fits the movement',
        'missing_checkpoints' => 'Missing checkpoints',
        'incomplete_routes' => 'Incomplete movements',
        'duplicates' => 'Possible duplicate movements',
    ];

    private const RANK = ['high' => 0, 'medium' => 1, 'low' => 2];

    public function __construct(private SettingsService $settings) {}

    public function build(int $eventId, User $user, int $days = 7): array
    {
        if (! $user->can('movements.view')) {
            return ['error' => 'The data checks are only available to users who can view movements.'];
        }

        $now = now();
        $until = $now->copy()->addDays($days);
        $movements = OperationsAttention::visibleMovements($eventId, $user)
            ->where(fn ($q) => $q->whereNull('window_start')->orWhereBetween('window_start', [$now->copy()->subHours(6), $until]))
            ->whereDoesntHave('job', fn ($q) => $q->whereIn('status', ['completed', 'cancelled']))
            ->with([
                'team:id,team_name,code', 'job:id,job_id,movement_id,status', 'flight:id,flight_number,scheduled_at',
                'vehicle', 'driver', 'fieldSupervisor.roles:id,name', 'units.vehicle', 'units.driver',
                'checkpointTemplate' => fn ($q) => $q->withTrashed()->withCount('checkpoints'),
            ])
            ->orderBy('window_start')
            ->get();

        $found = [
            'stale_flights' => $this->staleFlights($eventId, $now),
            'unconfirmed_flights' => $this->unconfirmedFlights($eventId, $now),
            'no_job' => $this->noJob($movements, $now),
            'invalid_crew' => $this->invalidCrew($movements, $eventId),
            'missing_checkpoints' => $this->missingCheckpoints($movements, $eventId, $user),
            'incomplete_routes' => $this->incomplete($movements),
            'duplicates' => $this->duplicates($movements),
        ];

        $categories = collect(self::TITLES)->map(function (string $title, string $key) use ($found) {
            $items = $found[$key]->sortBy(fn ($i) => [self::RANK[$i['severity']], $i['sort'] ?? ''])->values();

            return [
                'key' => $key,
                'title' => $title,
                'count' => $items->count(),
                'high' => $items->where('severity', 'high')->count(),
                'items' => $items->take(self::ITEMS)->map(fn ($i) => collect($i)->except('sort')->all())->all(),
            ];
        })->filter(fn ($c) => $c['count'] > 0)->values();

        return [
            'checked_until' => $until->format('Y-m-d H:i'),
            'movements_checked' => $movements->count(),
            'problems' => $categories->sum('count'),
            'high' => $categories->sum('high'),
            'categories' => $categories->sortBy(fn ($c) => [-$c['high'], array_search($c['key'], array_keys(self::TITLES))])->values()->all(),
            'limits' => [
                'flight_stale_near_minutes' => $this->t('flight_stale_near_minutes'),
                'flight_stale_far_hours' => $this->t('flight_stale_far_hours'),
                'flight_unconfirmed_minutes' => $this->t('flight_unconfirmed_minutes'),
                'no_job_warn_hours' => $this->t('no_job_warn_hours'),
                'no_job_urgent_hours' => $this->t('no_job_urgent_hours'),
            ],
            'note' => 'Each item has a severity (high, medium, low) and the fact it is based on. Counts are complete; items are capped at '.self::ITEMS.' per category, most severe first. Limits can be changed in Setups → Settings → Conflict rules.',
        ];
    }

    private function t(string $key): int
    {
        return $this->settings->getThreshold($key);
    }

    private function flightItem(TeamFlight $f, string $severity, string $fact): array
    {
        return [
            'severity' => $severity,
            'flight' => $f->flight_number,
            'team' => $f->team?->team_name,
            'direction' => $f->direction,
            'scheduled' => $f->scheduled_at?->format('D j M H:i'),
            'last_refreshed' => $f->flight_synced_at?->format('D j M H:i') ?? 'never',
            'last_attempt_error' => $f->flight_sync_error,
            'status' => $f->flight_status,
            'fact' => $fact,
            'url' => '/event-teams',
            'sort' => $f->scheduled_at?->format('Y-m-d H:i'),
        ];
    }

    /** Flights the automatic refresh should have kept current, and later ones nobody has checked. */
    private function staleFlights(int $eventId, Carbon $now): Collection
    {
        $near = $this->t('flight_stale_near_minutes');
        $far = $this->t('flight_stale_far_hours');

        return TeamFlight::with('team:id,team_name')
            ->where('event_id', $eventId)
            ->whereNotNull('flight_number')->where('flight_number', '!=', 'BUS')
            ->whereBetween('scheduled_at', [$now->copy()->subHours(3), $now->copy()->addHours(72)])
            ->where(fn ($q) => $q->whereNull('flight_status')->orWhereNotIn('flight_status', ['landed', 'cancelled']))
            ->get()
            ->map(function (TeamFlight $f) use ($now, $near, $far) {
                $to = (int) round($now->diffInMinutes($f->scheduled_at, false));
                $age = $f->flight_synced_at ? (int) round($f->flight_synced_at->diffInMinutes($now)) : null;
                $ago = fn () => $age >= 120 ? round($age / 60, 1).' h' : "{$age} min";

                return match (true) {
                    abs($to) <= 180 && $age === null => $this->flightItem($f, 'high', 'Within 3 h of the flight and never refreshed: the flight source has not matched it (check the flight number), or the scheduler is not running.'),
                    abs($to) <= 180 && $age > $near => $this->flightItem($f, 'high', "Within 3 h of the flight, last refreshed {$ago()} ago (limit {$near} min); it should refresh every 30 min."),
                    $to > 180 && $to <= 720 && $age === null => $this->flightItem($f, 'high', 'Within 12 h and never refreshed: the flight source has not matched it.'),
                    $to > 180 && $to <= 720 && $age > $far * 60 => $this->flightItem($f, 'medium', "3–12 h out, last refreshed {$ago()} ago (limit {$far} h); it should refresh every 3 h."),
                    $to > 720 && $age === null => $this->flightItem($f, 'low', 'Within 72 h and never checked against the flight source; the automatic refresh starts 12 h before. Press Sync on Event Teams to check it now.'),
                    default => null,
                };
            })
            ->filter()
            ->values();
    }

    /** Past their scheduled time with no landing or departure recorded (only flights of the last day). */
    private function unconfirmedFlights(int $eventId, Carbon $now): Collection
    {
        $limit = $this->t('flight_unconfirmed_minutes');

        return TeamFlight::with('team:id,team_name')
            ->where('event_id', $eventId)
            ->whereNotNull('flight_number')->where('flight_number', '!=', 'BUS')
            ->whereBetween('scheduled_at', [$now->copy()->subDay(), $now->copy()->subMinutes($limit)])
            ->whereNull('actual_at')
            ->where(fn ($q) => $q->whereNull('flight_status')->orWhereNotIn('flight_status', ['landed', 'active', 'cancelled', 'diverted']))
            ->get()
            ->map(fn (TeamFlight $f) => $this->flightItem($f, 'medium',
                'Scheduled '.$f->scheduled_at->format('H:i').', '.round($f->scheduled_at->diffInMinutes($now) / 60, 1).' h ago, and no '
                .($f->direction === 'departure' ? 'departure' : 'landing').' is recorded (status: '.($f->flight_status ?? 'none').'). Times on the ground may be wrong.'))
            ->values();
    }

    private function movementItem(Movement $m, string $severity, string $fact): array
    {
        return [
            'severity' => $severity,
            'movement_code' => $m->code,
            'job_id' => $m->job?->job_id,
            'team' => $m->team?->team_name,
            'kind' => $m->kind,
            'starts' => $m->window_start?->format('D j M H:i'),
            'fact' => $fact,
            'url' => OperationsQueryService::movementUrl($m),
            'sort' => $m->window_start?->format('Y-m-d H:i'),
        ];
    }

    private function noJob(Collection $movements, Carbon $now): Collection
    {
        $warn = $this->t('no_job_warn_hours');
        $urgent = $this->t('no_job_urgent_hours');

        return $movements
            ->filter(fn (Movement $m) => ! $m->job && $m->window_start && $m->window_start->between($now, $now->copy()->addHours($warn)))
            ->map(function (Movement $m) use ($now, $urgent, $warn) {
                $hours = round($now->diffInMinutes($m->window_start) / 60, 1);
                $high = $hours <= $urgent;

                return $this->movementItem($m, $high ? 'high' : 'medium',
                    "Starts in {$hours} h with no job (limit ".($high ? $urgent : $warn).' h): supervisors can\'t see it in the app and it gets no reminders.');
            })
            ->values();
    }

    /** Crew already on the movement that the crew rules would no longer allow. */
    private function invalidCrew(Collection $movements, int $eventId): Collection
    {
        $vehiclePool = Vehicle::inEventPool($eventId)->pluck('vehicles.id')->flip();
        $driverPool = Driver::inEventPool($eventId)->pluck('drivers.id')->flip();
        $provider = fn (Movement $m, $owner) => $m->fleet_provider_id && $owner !== null && (int) $owner !== (int) $m->fleet_provider_id;

        return $movements->flatMap(function (Movement $m) use ($vehiclePool, $driverPool, $provider) {
            $problems = [];
            $vehicles = collect([$m->vehicle_id ? [$m->vehicle_id, $m->vehicle] : null])->filter()
                ->concat($m->units->filter(fn ($u) => $u->vehicle_id)->map(fn ($u) => [$u->vehicle_id, $u->vehicle]));
            foreach ($vehicles as [$id, $v]) {
                $name = $v?->code ?? $v?->plate_number ?? "vehicle #{$id}";
                $problems[] = match (true) {
                    ! $v => "{$name} no longer exists",
                    $v->status === 'maintenance' => "{$name} is in maintenance",
                    $provider($m, $v->provider_id) => "{$name} belongs to another provider",
                    ! $vehiclePool->has($id) => "{$name} is not in this event's fleet",
                    default => null,
                };
            }
            $drivers = collect([$m->driver_id ? [$m->driver_id, $m->driver] : null])->filter()
                ->concat($m->units->filter(fn ($u) => $u->driver_id)->map(fn ($u) => [$u->driver_id, $u->driver]));
            foreach ($drivers as [$id, $d]) {
                $name = $d?->name ?? "driver #{$id}";
                $problems[] = match (true) {
                    ! $d => "{$name} no longer exists",
                    in_array($d->status, ['off', 'rest'], true) => "{$name} is marked {$d->status}",
                    $provider($m, $d->provider_id) => "{$name} belongs to another provider",
                    ! $driverPool->has($id) => "{$name} is not in this event's fleet",
                    default => null,
                };
            }
            if ($m->field_supervisor_id) {
                $s = $m->fieldSupervisor;
                $problems[] = match (true) {
                    ! $s => "supervisor #{$m->field_supervisor_id} no longer exists",
                    ! $s->is_active => "{$s->name} is deactivated (can't sign in)",
                    ! $s->roles->contains('name', 'ground_control') => "{$s->name} is not a field supervisor (ground control) any more",
                    $provider($m, $s->fleet_provider_id) => "{$s->name} belongs to another provider",
                    default => null,
                };
            }

            $problems = array_values(array_filter($problems));

            return $problems ? [$this->movementItem($m, 'high', 'Assigned crew no longer fits: '.implode('; ', $problems).'.')] : [];
        })->values();
    }

    private function missingCheckpoints(Collection $movements, int $eventId, User $user): Collection
    {
        $fromMovements = $movements->filter(fn (Movement $m) => ! $m->job)->map(function (Movement $m) {
            $t = $m->checkpointTemplate;

            return match (true) {
                ! $m->checkpoint_template_id => $this->movementItem($m, 'medium', 'No checkpoint template: its job would have no steps.'),
                ! $t => $this->movementItem($m, 'medium', 'Its checkpoint template no longer exists.'),
                $t->trashed() => $this->movementItem($m, 'medium', "Its checkpoint template {$t->code} is in the recycle bin."),
                ! $t->checkpoints_count => $this->movementItem($m, 'medium', "Its checkpoint template {$t->code} has no steps."),
                default => null,
            };
        })->filter();

        // Live jobs with no steps at all: nothing to complete in the app.
        $jobs = $user->can('jobs.view')
            ? JobOperation::visibleTo($user, $eventId)
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereDoesntHave('checkpoints')
                ->whereIn('movement_id', $movements->pluck('id'))
                ->pluck('movement_id')
            : collect();
        $byId = $movements->keyBy('id');
        $fromJobs = $jobs->map(fn ($id) => $this->movementItem($byId[$id], 'high', 'Its job has no checkpoints: the supervisor has nothing to complete in the app.'));

        return $fromMovements->concat($fromJobs)->values();
    }

    private function incomplete(Collection $movements): Collection
    {
        return $movements->map(function (Movement $m) {
            $missing = array_keys(array_filter([
                'start time' => ! $m->window_start,
                'end time' => ! $m->window_end,
                'from' => blank($m->from_location),
                'to' => blank($m->to_location),
                'team' => ! $m->team_id,
            ]));

            return $missing ? $this->movementItem($m, $m->window_start ? 'medium' : 'high', 'Missing '.implode(', ', $missing).'.') : null;
        })->filter()->values();
    }

    /** Same team and kind for the same flight or match, or on the same route starting within 30 minutes. */
    private function duplicates(Collection $movements): Collection
    {
        $out = collect();
        $live = $movements->filter(fn (Movement $m) => $m->window_start && $m->team_id)->values();

        foreach ($live as $i => $a) {
            foreach ($live->slice($i + 1) as $b) {
                if ($a->team_id !== $b->team_id || $a->kind !== $b->kind) {
                    continue;
                }
                $fact = match (true) {
                    $a->flight_id && $a->flight_id === $b->flight_id => 'both for flight '.($a->flight?->flight_number ?? "#{$a->flight_id}"),
                    $a->match_id && $a->match_id === $b->match_id && $a->from_location === $b->from_location && $a->to_location === $b->to_location => 'both for the same match on the same route',
                    $a->from_location === $b->from_location && $a->to_location === $b->to_location
                        && abs($a->window_start->diffInMinutes($b->window_start)) <= 30 => 'same route, starting '.abs((int) $a->window_start->diffInMinutes($b->window_start)).' min apart',
                    default => null,
                };
                if ($fact) {
                    $item = $this->movementItem($a, 'medium', "{$a->code} and {$b->code}: same team and kind, {$fact}.");
                    $item['other_movement_code'] = $b->code;
                    $item['other_url'] = OperationsQueryService::movementUrl($b);
                    $out->push($item);
                }
            }
        }

        return $out;
    }
}
