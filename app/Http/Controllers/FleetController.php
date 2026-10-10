<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Driver;
use App\Models\FleetProvider;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\ContactRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * CRUD for the three Fleet tabs: vehicles, drivers and transport providers.
 */
class FleetController extends Controller
{
    /* ----------------------------- Vehicles ----------------------------- */

    public function storeVehicle(Request $request): RedirectResponse
    {
        $vehicle = Vehicle::create($this->pinProvider($request, $this->validateVehicle($request)));
        $this->addToActiveEvent($request, $vehicle->events());

        AuditLog::change('Vehicle created', $vehicle->code, ['provider_id' => $vehicle->provider_id], $vehicle);
        // back() keeps the ?tab= query the page uses to restore the active tab.
        return back()->with('success', 'Vehicle added');
    }

    public function updateVehicle(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $original = $vehicle->getOriginal();
        $vehicle->update($this->pinProvider($request, $this->validateVehicle($request, $vehicle)));

        AuditLog::change('Vehicle updated', $vehicle->code, AuditLog::changes($vehicle, $original), $vehicle);
        return back()->with('success', 'Vehicle updated');
    }

    public function destroyVehicle(Vehicle $vehicle): RedirectResponse
    {
        // movements.vehicle_id and jobs_operations.vehicle_id are ON DELETE SET NULL,
        // so the rows survive but silently lose their vehicle - warn instead.
        $assigned = $vehicle->movements()->count() + $vehicle->jobs()->count();

        if ($assigned > 0) {
            return back()->with(
                'error',
                "{$vehicle->code} is assigned to {$assigned} movement/job record(s). Reassign them first."
            );
        }

        $vehicle->delete();

        AuditLog::change('Vehicle deleted', $vehicle->code, ['provider_id' => $vehicle->provider_id]);
        return back()->with('success', 'Vehicle deleted');
    }

    /* ----------------------------- Supervisors -------------------------- */

    /** A field supervisor is a mobile-app login (ground_control) already tied to a provider and the active event. */
    public function storeSupervisor(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100', ContactRoles::rule($request->filled('contact_id') ? Contact::whereKey($request->input('contact_id'))->value('role') : null)],
            // An entry of the contacts directory this supervisor is, so it is not picked twice.
            'contact_id' => ['nullable', 'integer', Rule::exists('pma_contacts', 'id')->whereNull('user_id')],
            'provider_id' => [Rule::requiredIf(! $request->user()->isProviderRestricted()), 'nullable', 'exists:fleet_providers,id'],
        ]);
        $data = $this->pinProvider($request, $data);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'fleet_provider_id' => $data['provider_id'],
        ]);
        $user->assignRole('ground_control');
        $this->addToActiveEvent($request, $user->events());

        if (! empty($data['contact_id'])) {
            Contact::whereKey($data['contact_id'])->update(['user_id' => $user->id]);
        }

        AuditLog::change('Supervisor created', $user->email, ['provider_id' => $user->fleet_provider_id, 'roles' => ['ground_control']], $user);

        return back()->with('success', 'Supervisor added');
    }

    public function updateSupervisor(Request $request, User $user): RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($user->hasRole('ground_control'), 404);
        abort_if($viewer->isProviderRestricted() && (int) $user->fleet_provider_id !== (int) $viewer->fleet_provider_id, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100', ContactRoles::rule($user->job_title)],
        ]);

        $original = $user->getOriginal();
        $user->update($data);

        // The directory entry this login came from follows along.
        Contact::where('user_id', $user->id)->update(array_filter([
            'name' => $user->name,
            'phone' => $user->phone,
            'role' => $user->job_title,
        ], fn ($v) => $v !== null));

        AuditLog::change('Supervisor updated', $user->email, AuditLog::changes($user, $original), $user);

        return back()->with('success', 'Supervisor updated');
    }

    /* ------------------------------ Drivers ----------------------------- */

    public function storeDriver(Request $request): RedirectResponse
    {
        $driver = Driver::create($this->pinProvider($request, $this->validateDriver($request)));
        $this->addToActiveEvent($request, $driver->events());

        AuditLog::change('Driver created', $driver->name, ['provider_id' => $driver->provider_id], $driver);
        return back()->with('success', 'Driver added');
    }

    public function updateDriver(Request $request, Driver $driver): RedirectResponse
    {
        $original = $driver->getOriginal();
        $driver->update($this->pinProvider($request, $this->validateDriver($request)));

        AuditLog::change('Driver updated', $driver->name, AuditLog::changes($driver, $original), $driver);
        return back()->with('success', 'Driver updated');
    }

    public function destroyDriver(Driver $driver): RedirectResponse
    {
        $assigned = $driver->movements()->count() + $driver->jobs()->count();

        if ($assigned > 0) {
            return back()->with(
                'error',
                "{$driver->name} is assigned to {$assigned} movement/job record(s). Reassign them first."
            );
        }

        $driver->delete();

        AuditLog::change('Driver deleted', $driver->name, ['provider_id' => $driver->provider_id]);
        return back()->with('success', 'Driver deleted');
    }

    /* ----------------------------- Providers ---------------------------- */

    public function storeProvider(Request $request): RedirectResponse
    {
        abort_if($request->user()->isProviderRestricted(), 403, 'A provider account cannot add other providers.');

        $provider = FleetProvider::create($this->validateProvider($request));

        AuditLog::change('Provider created', $provider->code, ['name' => $provider->name], $provider);
        return back()->with('success', 'Provider added');
    }

    public function updateProvider(Request $request, FleetProvider $provider): RedirectResponse
    {
        $original = $provider->getOriginal();
        $provider->update($this->validateProvider($request, $provider));

        AuditLog::change('Provider updated', $provider->code, AuditLog::changes($provider, $original), $provider);
        return back()->with('success', 'Provider updated');
    }

    public function destroyProvider(FleetProvider $provider): RedirectResponse
    {
        abort_if(Auth::user()->isProviderRestricted(), 403, 'A provider account cannot delete its provider.');

        // vehicles.provider_id / drivers.provider_id have no FK constraint, so a
        // delete here would silently orphan them rather than fail.
        $vehicles = $provider->vehicles()->count();
        $drivers = $provider->drivers()->count();

        if ($vehicles + $drivers > 0) {
            return back()->with(
                'error',
                "{$provider->name} still has {$vehicles} vehicle(s) and {$drivers} driver(s). Reassign them first."
            );
        }

        $provider->delete();

        AuditLog::change('Provider deleted', $provider->code, ['name' => $provider->name]);
        return back()->with('success', 'Provider deleted');
    }

    /* ----------------------------- Event fleet -------------------------- */

    /** Deletes the ticked rows, skipping any still in use, and reports what was left. */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['vehicle', 'driver', 'provider'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        if ($data['type'] === 'provider') {
            abort_if($request->user()->isProviderRestricted(), 403, 'A provider account cannot delete its provider.');
        }

        // Scoped lookup, so a provider account can only reach its own rows.
        $rows = match ($data['type']) {
            'vehicle' => Vehicle::whereIn('id', $data['ids'])->get(),
            'driver' => Driver::whereIn('id', $data['ids'])->get(),
            'provider' => FleetProvider::whereIn('id', $data['ids'])->get(),
        };

        $deleted = 0;
        $skipped = [];

        foreach ($rows as $row) {
            $inUse = $data['type'] === 'provider'
                ? $row->vehicles()->count() + $row->drivers()->count()
                : $row->movements()->count() + $row->jobs()->count();

            if ($inUse > 0) {
                $skipped[] = $row->code ?? $row->name;

                continue;
            }

            $row->delete();
            $deleted++;
        }

        $message = "{$deleted} deleted";

        AuditLog::change("Bulk deleted {$data['type']}s", "{$deleted} {$data['type']}(s)", ['requested' => count($data['ids']), 'skipped_in_use' => $skipped]);
        if ($skipped) {
            return back()->with('error', "{$message}. Skipped because they are still in use: ".implode(', ', $skipped).'.');
        }

        return back()->with('success', $message);
    }

    /** Admin only: moves the ticked vehicles or drivers to one provider. */
    public function bulkAssignProvider(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403, 'Only an admin can reassign providers.');

        $data = $request->validate([
            'type' => ['required', Rule::in(['vehicle', 'driver'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'provider_id' => ['required', 'exists:fleet_providers,id'],
        ]);

        $model = $data['type'] === 'vehicle' ? Vehicle::class : Driver::class;
        $count = $model::whereIn('id', $data['ids'])->update(['provider_id' => $data['provider_id']]);

        AuditLog::change("Bulk provider change ({$data['type']}s)", "{$count} {$data['type']}(s)", ['ids' => $data['ids'], 'provider_id' => (int) $data['provider_id']]);
        return back()->with('success', "{$count} updated");
    }

    /* ----------------------------- Event fleet (pool) -------------------- */

    /** Puts a vehicle or driver in, or takes it out of, the active event's fleet. */
    public function setPool(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['vehicle', 'driver'])],
            'id' => ['required', 'integer'],
            'in_pool' => ['required', 'boolean'],
        ]);

        $eventId = (int) $request->session()->get('active_event_id');
        abort_unless($request->user()->canAccessEvent($eventId), 403, 'Pick an event you can access first.');

        // Scoped lookup: a provider account can only toggle its own fleet.
        $resource = $data['type'] === 'vehicle' ? Vehicle::findOrFail($data['id']) : Driver::findOrFail($data['id']);
        $events = $resource->events();

        $data['in_pool'] ? $events->syncWithoutDetaching([$eventId]) : $events->detach($eventId);

        return back();
    }

    /** A provider's own people always work for that provider. */
    private function pinProvider(Request $request, array $data): array
    {
        $user = $request->user();

        if ($user->isProviderRestricted()) {
            abort_unless($user->fleet_provider_id, 403, 'Your account is not linked to a provider.');
            $data['provider_id'] = $user->fleet_provider_id;
        }

        return $data;
    }

    /** New fleet is offered for the event the user is working in. */
    private function addToActiveEvent(Request $request, $events): void
    {
        $eventId = (int) $request->session()->get('active_event_id');

        if ($eventId && $request->user()->canAccessEvent($eventId)) {
            $events->syncWithoutDetaching([$eventId]);
        }
    }

    /* ----------------------------- Validation --------------------------- */

    private function validateVehicle(Request $request, ?Vehicle $vehicle = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('vehicles', 'code')->ignore($vehicle)],
            'vehicle_type' => ['required', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:200'],
            'plate_number' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', Rule::in(['Team', 'Official', 'VIP', 'Media'])],
            'fuel_level' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['available', 'on_job', 'maintenance', 'standby'])],
            // Provider accounts are pinned to their own provider afterwards.
            'provider_id' => [Rule::requiredIf(! $request->user()->isProviderRestricted()), 'nullable', 'exists:fleet_providers,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function validateDriver(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'license_number' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['available', 'on_shift', 'off', 'rest'])],
            'provider_id' => ['nullable', 'exists:fleet_providers,id'],
        ]);
    }

    private function validateProvider(Request $request, ?FleetProvider $provider = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('fleet_providers', 'code')->ignore($provider)],
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:9.9'],
            'status' => ['required', Rule::in(['active', 'standby'])],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
