<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Movement;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Where each driver actually is in their day, read from today's assigned
 * movements and their jobs rather than from a manually kept status field.
 *
 * The stored status only carries what planning knows and the schedule can't:
 * a driver marked off or on a rest day stays that way whatever is assigned.
 */
class DriverDayStatusService
{
    /** A break between two jobs at least this long is rest rather than standby. */
    public const REST_GAP_MINUTES = 120;

    /**
     * @param  Collection<int, Driver>  $drivers
     * @return array<int, array{state: string, label: string, detail: string, jobs_today: int}> keyed by driver id
     */
    public function forDrivers(Collection $drivers, ?Carbon $now = null): array
    {
        $now ??= now();
        $start = $now->copy()->startOfDay();
        $end = $now->copy()->endOfDay();

        $driverIds = $drivers->pluck('id');
        $movements = Movement::with(['job:id,movement_id,status', 'units:id,movement_id,driver_id'])
            ->where(fn ($q) => $q->whereIn('driver_id', $driverIds)
                ->orWhereHas('units', fn ($u) => $u->whereIn('driver_id', $driverIds)))
            ->where('status', '!=', 'cancelled')
            // Includes a run that started last night and ends this morning.
            ->where(fn ($q) => $q->whereBetween('window_start', [$start, $end])
                ->orWhereBetween('window_end', [$start, $end]))
            ->orderBy('window_start')
            ->get(['id', 'code', 'driver_id', 'from_location', 'to_location', 'window_start', 'window_end', 'status'])
            ->reject(fn (Movement $m) => $m->job?->status === 'cancelled');

        // A driver on an extra unit is just as busy as the lead driver.
        $byDriver = [];
        foreach ($movements as $m) {
            foreach ($m->resourceIds('driver_id') as $id) {
                $byDriver[$id][] = $m;
            }
        }

        return $drivers
            ->mapWithKeys(fn (Driver $d) => [$d->id => $this->resolve($d, collect($byDriver[$d->id] ?? []), $now)])
            ->all();
    }

    /**
     * @param  Collection<int, Movement>  $today
     */
    private function resolve(Driver $driver, Collection $today, Carbon $now): array
    {
        $count = $today->count();

        if (in_array($driver->status, ['off', 'rest'], true)) {
            $label = $driver->status === 'off' ? 'Off' : 'Rest day';

            return $this->state($driver->status, $label, $count
                ? "Marked {$label} but assigned {$count} job(s) today"
                : 'Set by planning', $count);
        }

        if ($count === 0) {
            return $this->state('idle', 'Idle', 'No jobs today', 0);
        }

        $finished = fn (Movement $m) => $m->job?->status !== 'in-progress'
            && ($m->job?->status === 'completed' || $m->status === 'completed' || ($m->window_end && $m->window_end->lte($now)));

        $live = $today->first(fn (Movement $m) => $m->job?->status === 'in-progress'
            || (! $finished($m) && $m->window_start?->lte($now)));

        if ($live) {
            return $this->state('on_job', 'On Job', sprintf('%s · %s → %s%s',
                $live->code, $live->from_location ?? '—', $live->to_location ?? '—',
                $live->window_end ? ' · until '.$live->window_end->format('H:i') : ''), $count);
        }

        $next = $today->first(fn (Movement $m) => ! $finished($m));
        $previous = $today->filter($finished)->sortBy('window_end')->last();

        if (! $next) {
            return $this->state('done', 'Shift Done', sprintf('%d job(s) today · last ended %s',
                $count, $previous?->window_end?->format('H:i') ?? '—'), $count);
        }

        $nextAt = "Next {$next->code} at ".$next->window_start->format('H:i');

        if (! $previous) {
            return $this->state('scheduled', 'Scheduled', "First job {$next->code} at ".$next->window_start->format('H:i'), $count);
        }

        $break = $previous->window_end?->diffInMinutes($next->window_start) ?? 0;

        return $break >= self::REST_GAP_MINUTES
            ? $this->state('rest', 'Rest', $nextAt, $count)
            : $this->state('on_shift', 'On Shift', $nextAt, $count);
    }

    private function state(string $state, string $label, string $detail, int $jobsToday): array
    {
        return ['state' => $state, 'label' => $label, 'detail' => $detail, 'jobs_today' => $jobsToday];
    }
}
