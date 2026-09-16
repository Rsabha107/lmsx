<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\FleetProvider;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD for the three Fleet tabs: vehicles, drivers and transport providers.
 */
class FleetController extends Controller
{
    /* ----------------------------- Vehicles ----------------------------- */

    public function storeVehicle(Request $request): RedirectResponse
    {
        Vehicle::create($this->validateVehicle($request));

        // back() keeps the ?tab= query the page uses to restore the active tab.
        return back()->with('success', 'Vehicle added');
    }

    public function updateVehicle(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update($this->validateVehicle($request, $vehicle));

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

        return back()->with('success', 'Vehicle deleted');
    }

    /* ------------------------------ Drivers ----------------------------- */

    public function storeDriver(Request $request): RedirectResponse
    {
        Driver::create($this->validateDriver($request));

        return back()->with('success', 'Driver added');
    }

    public function updateDriver(Request $request, Driver $driver): RedirectResponse
    {
        $driver->update($this->validateDriver($request));

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

        return back()->with('success', 'Driver deleted');
    }

    /* ----------------------------- Providers ---------------------------- */

    public function storeProvider(Request $request): RedirectResponse
    {
        FleetProvider::create($this->validateProvider($request));

        return back()->with('success', 'Provider added');
    }

    public function updateProvider(Request $request, FleetProvider $provider): RedirectResponse
    {
        $provider->update($this->validateProvider($request, $provider));

        return back()->with('success', 'Provider updated');
    }

    public function destroyProvider(FleetProvider $provider): RedirectResponse
    {
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

        return back()->with('success', 'Provider deleted');
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
            'provider_id' => ['nullable', 'exists:fleet_providers,id'],
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
