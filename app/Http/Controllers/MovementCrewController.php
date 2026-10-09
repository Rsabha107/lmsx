<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Event;
use App\Models\FleetProvider;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ConflictDetectionService;
use App\Services\JobLifecycleService;
use App\Support\CrewEligibility;
use Carbon\Carbon;
use Closure;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $providerNames = FleetProvider::withoutGlobalScopes()->pluck('name', 'id');

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
            ->map(function (Movement $m) use ($day, $spanOf, $schedule, $vehicleLabel, $unitsOf, $extraSupervisorsOf, $providerNames) {
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
                    'fleet_provider_id' => $m->fleet_provider_id,
                    'provider' => $providerNames[$m->fleet_provider_id] ?? null,
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
            'vehicles' => Vehicle::inEventPool($eventId)->select('id', 'code', 'plate_number', 'vehicle_type', 'capacity', 'provider_id')->orderBy('code')->get(),
            'drivers' => Driver::inEventPool($eventId)->select('id', 'name', 'phone', 'provider_id')->orderBy('name')->get(),
            'supervisors' => $this->supervisors()->select('id', 'name', 'fleet_provider_id')->orderBy('name')->get(),
            // Only people who can re-home a movement are offered the provider switch.
            'providers' => $user->can('plans.manage') ? FleetProvider::orderBy('name')->get(['id', 'name']) : [],
        ]);
    }

    /** One day's resource allocation sheet for the service provider. */
    public function export(Request $request): StreamedResponse
    {
        $eventId = $request->session()->get('active_event_id');
        $user = $request->user();

        try {
            $day = Carbon::parse($request->query('date', now()->toDateString()))->startOfDay();
        } catch (\Exception) {
            $day = now()->startOfDay();
        }

        $movements = Movement::where('event_id', $eventId)
            ->when(
                ! $user->can('movements.view-all-functional-areas'),
                fn ($q) => $q->whereIn('functional_area', $user->functionalAreaCodes()),
            )
            ->with([
                'team:id,code,team_name', 'job:id,movement_id,job_id,status', 'flight:id,scheduled_at', 'match:id,kick_off',
                'vehicle:id,code,plate_number', 'driver:id,name', 'fieldSupervisor:id,name',
                'units.vehicle:id,code,plate_number', 'units.driver:id,name', 'extraSupervisors:id,name',
            ])
            ->where('window_start', '>=', $day)
            ->where('window_start', '<', $day->copy()->addDay())
            ->orderBy('window_start')
            ->orderBy('id')
            ->get();

        $names = fn (array $values) => implode(', ', array_values(array_unique(array_filter($values))));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Allocation');
        $sheet->setCellValue('A1', 'PMA RESOURCE ALLOCATION SCHEDULE ' . strtoupper($day->format('j F Y')));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $headers = ['Job ID', 'Activity Type', 'PMA', 'Arrival Date', 'Arrival Time', 'Match KO time', 'Truck Report', 'FROM (VENUE)', 'TO (VENUE)', 'Driver', 'Supervisor', 'Crew', 'Truck Plate #', 'Status', 'Notes'];
        $sheet->fromArray($headers, null, 'A3');
        $head = $sheet->getStyle('A3:O3');
        $head->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $head->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F4E79');
        $head->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

        $row = 4;
        foreach ($movements as $m) {
            $onFlight = in_array($m->kind, ['arrival', 'departure'], true);
            $plates = fn ($v) => $v ? ($v->plate_number ?? $v->code) : null;

            $sheet->fromArray([
                $m->job?->job_id ?? $m->code,
                $m->kind === 'match' ? 'MATCH DAY' : strtoupper(str_replace('_', ' ', (string) $m->kind)),
                $m->team?->team_name,
                $m->window_start->format('l, F j, Y'),
                $onFlight ? $m->flight?->scheduled_at?->format('H:i') : null,
                $m->kind === 'match' ? $m->match?->kick_off?->format('H:i') : null,
                null,
                $m->from_location,
                $m->to_location,
                $names([$m->driver?->name, ...$m->units->map(fn ($u) => $u->driver?->name)->all()]),
                $names([$m->fieldSupervisor?->name, ...$m->extraSupervisors->pluck('name')->all()]),
                null,
                $names([$plates($m->vehicle), ...$m->units->map(fn ($u) => $plates($u->vehicle))->all()]),
                $m->job ? JobOperation::statusLabel($m->job->status) : 'Pending',
                $m->notes,
            ], null, 'A' . $row);
            $row++;
        }

        foreach (range('A', 'O') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A4');

        $code = $eventId ? Event::find($eventId)?->short_name : null;
        $filename = implode('_', array_filter([
            'PMA',
            $code ? preg_replace('/[^A-Za-z0-9]/', '', $code) : null,
            $day->format('dmY'),
            'RESOURCE_ALLOCATION',
        ])) . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function update(Request $request, Movement $movement, JobLifecycleService $lifecycle, ConflictDetectionService $conflicts): RedirectResponse
    {
        $this->authorize('view', $movement);

        // All three are required keys so a partial payload can't silently clear a role.
        $crewRule = fn (string $role) => function (string $attribute, mixed $value, Closure $fail) use ($movement, $role) {
            if ($value !== null && ($why = CrewEligibility::violation($movement, $role, (int) $value))) {
                $fail($why);
            }
        };

        $validated = $request->validate([
            'vehicle_id' => ['present', 'nullable', 'integer', 'exists:vehicles,id', $crewRule('vehicle')],
            'driver_id' => ['present', 'nullable', 'integer', 'exists:drivers,id', $crewRule('driver')],
            'field_supervisor_id' => ['present', 'nullable', 'integer', $crewRule('supervisor'), function (string $attribute, mixed $value, Closure $fail) use ($movement) {
                // Keeping whoever planning already put there is always allowed.
                if ($value !== null && (int) $value !== (int) $movement->field_supervisor_id && ! $this->supervisors()->whereKey($value)->exists()) {
                    $fail('The selected supervisor cannot run jobs in the mobile app.');
                }
            }],
            // Optional so older clients that only send the lead crew leave the extras alone.
            'units' => ['sometimes', 'array', 'max:10'],
            'units.*.vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id', $crewRule('vehicle')],
            'units.*.driver_id' => ['nullable', 'integer', 'exists:drivers,id', $crewRule('driver')],
            'supervisors' => ['sometimes', 'array', 'max:10'],
            'supervisors.*' => ['integer', $crewRule('supervisor'), function (string $attribute, mixed $value, Closure $fail) use ($movement) {
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

    /** Hands a movement to another provider; its crew stays until that provider reassigns it. */
    public function updateProvider(Request $request, Movement $movement): RedirectResponse
    {
        $this->authorize('view', $movement);

        $validated = $request->validate([
            'fleet_provider_id' => ['present', 'nullable', 'integer', Rule::exists('fleet_providers', 'id')],
        ]);

        $movement->update($validated);

        $mismatched = $validated['fleet_provider_id'] && (
            ($movement->vehicle && (int) $movement->vehicle->provider_id !== (int) $validated['fleet_provider_id'])
            || ($movement->driver && (int) $movement->driver->provider_id !== (int) $validated['fleet_provider_id'])
            || ($movement->fieldSupervisor && (int) $movement->fieldSupervisor->fleet_provider_id !== (int) $validated['fleet_provider_id'])
        );

        return back()->with(
            $mismatched ? 'warning' : 'success',
            $mismatched
                ? "Provider changed for {$movement->code}, but its vehicle, driver or supervisor belongs to another provider - reassign them."
                : "Provider updated for {$movement->code}.",
        );
    }

    /** A supervisor has to be able to work the job in the mobile app, and belong to the movement's provider. */
    private function supervisors()
    {
        $query = User::permission('jobs.view');
        $user = Auth::user();

        return $user?->isProviderRestricted()
            ? $query->where('users.fleet_provider_id', $user->fleet_provider_id ?? 0)
            : $query;
    }
}
