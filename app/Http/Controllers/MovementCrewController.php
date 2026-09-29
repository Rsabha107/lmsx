<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Movement;
use App\Models\User;
use App\Models\Vehicle;
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

    public function index(Request $request): Response
    {
        $eventId = $request->session()->get('active_event_id');
        $user = $request->user();

        try {
            $date = Carbon::parse($request->query('date', now()->toDateString()))->toDateString();
        } catch (\Exception) {
            $date = now()->toDateString();
        }

        $movements = Movement::with(['team:id,code,team_name', 'job:id,movement_id,job_id,status'])
            ->where('event_id', $eventId)
            ->whereDate('window_start', $date)
            ->when(
                ! $user->can('movements.view-all-functional-areas'),
                fn ($q) => $q->whereIn('functional_area', $user->functionalAreaCodes()),
            )
            ->orderBy('window_start')
            ->get()
            ->map(fn (Movement $m) => [
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
                'job_id' => $m->job?->job_id,
                'job_status' => $m->job?->status,
            ]);

        return Inertia::render('CrewAssignment', [
            'movements' => $movements,
            'date' => $date,
            'vehicles' => Vehicle::select('id', 'code', 'plate_number', 'vehicle_type', 'capacity')->orderBy('code')->get(),
            'drivers' => Driver::select('id', 'name', 'phone')->orderBy('name')->get(),
            'supervisors' => $this->supervisors()->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Movement $movement, JobLifecycleService $lifecycle): RedirectResponse
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
        ]);

        $changed = $lifecycle->assignMovementCrew(
            $movement,
            $validated['vehicle_id'] ?? null,
            $validated['driver_id'] ?? null,
            $validated['field_supervisor_id'] ?? null,
        );

        return back()->with('success', $changed ? "Crew updated for {$movement->code}." : 'No changes to save.');
    }

    /** A supervisor has to be able to work the job in the mobile app. */
    private function supervisors()
    {
        return User::permission('jobs.view');
    }
}
