<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Movement;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ConflictDetectionService;
use App\Services\JobLifecycleService;
use Carbon\Carbon;
use Closure;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The crew agency's screen: pick the vehicle, driver and supervisor for each
 * movement, without access to anything else on it.
 */
class MovementCrewController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, ConflictDetectionService $conflicts): Response
    {
        $eventId = $request->session()->get('active_event_id');
        $user = $request->user();

        try {
            $date = Carbon::parse($request->query('date', now()->toDateString()))->toDateString();
        } catch (\Exception) {
            $date = now()->toDateString();
        }
        $day = Carbon::parse($date)->startOfDay();

        $scoped = fn () => Movement::where('event_id', $eventId)
            ->when(
                ! $user->can('movements.view-all-functional-areas'),
                fn ($q) => $q->whereIn('functional_area', $user->functionalAreaCodes()),
            );
        $crew = ['vehicle:id,code,plate_number,vehicle_type', 'driver:id,name', 'fieldSupervisor:id,name', 'units.vehicle:id,code,plate_number,vehicle_type', 'units.driver:id,name', 'extraSupervisors:id,name'];

        // Same spans and double-booking rules as Planning's Conflicts tab.
        $schedule = $eventId ? $conflicts->crewSchedule((int) $eventId) : [];
        $spanOf = fn (Movement $m) => [
            $schedule[$m->id]['start'] ?? $m->window_start,
            $schedule[$m->id]['end'] ?? $m->window_end ?? $m->window_start?->copy()->addMinutes(30),
        ];
        $vehicleLabel = fn (Movement $m) => $m->vehicle ? ($m->vehicle->code ?? $m->vehicle->plate_number ?? $m->vehicle->vehicle_type) : null;
        $unitsOf = fn (Movement $m) => $m->units->map(fn ($u) => [
            'vehicle_id' => $u->vehicle_id,
            'driver_id' => $u->driver_id,
            'vehicle' => $u->vehicle ? ($u->vehicle->code ?? $u->vehicle->plate_number ?? $u->vehicle->vehicle_type) : null,
            'driver' => $u->driver?->name,
        ])->values()->all();
        $extraSupervisorsOf = fn (Movement $m) => $m->extraSupervisors->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values()->all();

        // The day's movements, plus last night's that are still running after midnight.
        $movements = $scoped()
            ->with(['team:id,code,team_name', 'job:id,movement_id,job_id,status', ...$crew])
            ->where('window_start', '>=', $day->copy()->subDay())
            ->where('window_start', '<', $day->copy()->addDay())
            ->orderBy('window_start')
            ->get()
            ->map(function (Movement $m) use ($day, $spanOf, $schedule, $vehicleLabel, $unitsOf, $extraSupervisorsOf) {
                [$start, $end] = $spanOf($m);
                $carryOver = $m->window_start->lt($day);
                if ($carryOver && ! $end?->gt($day)) {
                    return null;
                }

                return [
                    'id' => $m->id,
                    'code' => $m->code,
                    'kind' => $m->kind,
                    'team' => $m->team?->team_name,
                    'team_code' => $m->team?->code,
                    'from' => $m->from_location,
                    'to' => $m->to_location,
                    'start' => $m->window_start?->format('H:i'),
                    'end' => $m->window_end?->format('H:i'),
                    'pax' => $m->passengers,
                    'flight_number' => $m->flight_number,
                    'functional_area' => $m->functional_area,
                    'vehicle_id' => $m->vehicle_id,
                    'driver_id' => $m->driver_id,
                    'field_supervisor_id' => $m->field_supervisor_id,
                    'vehicle' => $vehicleLabel($m),
                    'driver' => $m->driver?->name,
                    'supervisor' => $m->fieldSupervisor?->name,
                    'units' => $unitsOf($m),
                    'extra_supervisors' => $extraSupervisorsOf($m),
                    'job_id' => $m->job?->job_id,
                    'job_status' => $m->job?->status,
                    'span_start' => $start?->format('Y-m-d H:i'),
                    'span_end' => $end?->format('Y-m-d H:i'),
                    'carry_over' => $carryOver,
                    'clashes' => $schedule[$m->id]['clashes'] ?? [],
                ];
            })
            ->filter()
            ->values();

        $days = $scoped()
            ->whereNotNull('window_start')
            ->get(['id', 'window_start', 'vehicle_id', 'driver_id', 'field_supervisor_id'])
            ->groupBy(fn (Movement $m) => $m->window_start->toDateString())
            ->map(fn ($group, $key) => [
                'date' => $key,
                'total' => $group->count(),
                'unassigned' => $group->filter(fn ($m) => ! $m->vehicle_id || ! $m->driver_id || ! $m->field_supervisor_id)->count(),
                'clashes' => $group->filter(fn ($m) => ! empty($schedule[$m->id]['clashes']))->count(),
            ])
            ->sortKeys()
            ->values();

        $weekStart = $day->copy()->startOfWeek(Carbon::MONDAY);
        $week = $scoped()
            ->with($crew)
            ->where('window_start', '>=', $weekStart)
            ->where('window_start', '<', $weekStart->copy()->addWeek())
            ->get()
            ->map(function (Movement $m) use ($spanOf, $schedule, $vehicleLabel, $unitsOf, $extraSupervisorsOf) {
                [$start, $end] = $spanOf($m);

                return [
                    'id' => $m->id,
                    'date' => $m->window_start->toDateString(),
                    'start' => $start?->format('Y-m-d H:i'),
                    'end' => $end?->format('Y-m-d H:i'),
                    'minutes' => $start && $end ? (int) $start->diffInMinutes($end, true) : 0,
                    'vehicle_id' => $m->vehicle_id,
                    'driver_id' => $m->driver_id,
                    'field_supervisor_id' => $m->field_supervisor_id,
                    'vehicle' => $vehicleLabel($m),
                    'driver' => $m->driver?->name,
                    'supervisor' => $m->fieldSupervisor?->name,
                    'units' => $unitsOf($m),
                    'extra_supervisors' => $extraSupervisorsOf($m),
                    'clashes' => array_keys($schedule[$m->id]['clashes'] ?? []),
                ];
            })
            ->values();

        return Inertia::render('CrewAssignment', [
            'movements' => $movements,
            'date' => $date,
            'days' => $days,
            'week' => ['start' => $weekStart->toDateString(), 'slots' => $week],
            'vehicles' => Vehicle::select('id', 'code', 'plate_number', 'vehicle_type', 'capacity')->orderBy('code')->get(),
            'drivers' => Driver::select('id', 'name', 'phone')->orderBy('name')->get(),
            'supervisors' => $this->supervisors()->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Movement $movement, JobLifecycleService $lifecycle, ConflictDetectionService $conflicts): RedirectResponse
    {
        $this->authorize('view', $movement);

        // All three are required keys so a partial payload can't silently clear a role.
        $validated = $request->validate([
            'vehicle_id' => ['present', 'nullable', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['present', 'nullable', 'integer', 'exists:drivers,id'],
            'field_supervisor_id' => ['present', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($movement) {
                // Keeping whoever planning already put there is always allowed.
                if ($value !== null && (int) $value !== (int) $movement->field_supervisor_id && ! $this->supervisors()->whereKey($value)->exists()) {
                    $fail('The selected supervisor cannot run jobs in the mobile app.');
                }
            }],
            // Optional so older clients that only send the lead crew leave the extras alone.
            'units' => ['sometimes', 'array', 'max:10'],
            'units.*.vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'units.*.driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'supervisors' => ['sometimes', 'array', 'max:10'],
            'supervisors.*' => ['integer', function (string $attribute, mixed $value, Closure $fail) use ($movement) {
                $kept = $movement->extraSupervisors()->whereKey($value)->exists();
                if (! $kept && ! $this->supervisors()->whereKey($value)->exists()) {
                    $fail('A selected supervisor cannot run jobs in the mobile app.');
                }
            }],
        ]);

        $changed = $lifecycle->assignMovementCrew(
            $movement,
            $validated['vehicle_id'] ?? null,
            $validated['driver_id'] ?? null,
            $validated['field_supervisor_id'] ?? null,
            array_key_exists('units', $validated) ? $validated['units'] : null,
            array_key_exists('supervisors', $validated) ? $validated['supervisors'] : null,
        );

        // Saved regardless: a clash is a warning for the planner, not a block.
        $clashes = collect($conflicts->crewSchedule($movement->event_id)[$movement->id]['clashes'] ?? [])->flatten();
        if ($changed && $clashes->isNotEmpty()) {
            $more = $clashes->count() > 1 ? ' (+' . ($clashes->count() - 1) . ' more)' : '';

            return back()->with('warning', "Crew updated for {$movement->code}, but: {$clashes->first()}{$more}");
        }

        return back()->with('success', $changed ? "Crew updated for {$movement->code}." : 'No changes to save.');
    }

    /** A supervisor has to be able to work the job in the mobile app. */
    private function supervisors()
    {
        return User::permission('jobs.view');
    }
}
