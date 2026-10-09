<?php

namespace Tests\Concerns;

use App\Models\Country;
use App\Models\Driver;
use App\Models\Event;
use App\Models\FleetProvider;
use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

trait CreatesOperationsFixtures
{
    private ?FleetProvider $fixtureProvider = null;

    /** The provider every fixture event, vehicle, driver and agency user belongs to unless told otherwise. */
    protected function fixtureProvider(): FleetProvider
    {
        return $this->fixtureProvider ??= FleetProvider::withoutGlobalScopes()->create(['code' => 'GWC', 'name' => 'GWC']);
    }

    protected function createProvider(string $name): FleetProvider
    {
        return FleetProvider::withoutGlobalScopes()->create(['code' => strtoupper(Str::random(4)), 'name' => $name]);
    }

    /** @param array<string, mixed> $attributes */
    protected function createVehicle(Event $event, array $attributes = []): Vehicle
    {
        $vehicle = Vehicle::create($attributes + ['code' => 'V-'.Str::random(5), 'provider_id' => $this->fixtureProvider()->id]);
        $vehicle->events()->attach($event->id);

        return $vehicle;
    }

    /** @param array<string, mixed> $attributes */
    protected function createDriver(Event $event, array $attributes = []): Driver
    {
        $driver = Driver::create($attributes + ['name' => 'Driver '.Str::random(5), 'status' => 'available', 'provider_id' => $this->fixtureProvider()->id]);
        $driver->events()->attach($event->id);

        return $driver;
    }

    protected function createProviderUser(string $role, ?FleetProvider $provider = null): User
    {
        $user = User::factory()->create(['fleet_provider_id' => ($provider ?? $this->fixtureProvider())->id]);
        $user->assignRole($role);

        return $user;
    }

    protected function createEvent(): Event
    {
        return Event::create(['name' => 'Test Event '.Str::random(6), 'fleet_provider_id' => $this->fixtureProvider()->id]);
    }

    protected function createTeam(Event $event): Team
    {
        $country = Country::create([
            'country_code' => strtoupper(Str::random(3)),
            'country_name' => 'Testland',
        ]);

        return Team::create([
            'code' => strtoupper(Str::random(3)),
            'team_name' => 'Test Team '.Str::random(4),
            'country_id' => $country->country_code,
            'event_id' => $event->id,
        ]);
    }

    protected function createPlan(Event $event): Plan
    {
        return Plan::create([
            'code' => 'PLN-'.Str::random(6),
            'name' => 'Test Plan',
            'date' => now(),
            'event_id' => $event->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createMovement(Event $event, Plan $plan, Team $team, array $overrides = []): Movement
    {
        return Movement::create(array_merge([
            'code' => 'MV-'.Str::random(8),
            'event_id' => $event->id,
            'plan_id' => $plan->id,
            'team_id' => $team->id,
            'kind' => 'transfer',
            'functional_area' => 'LOG',
            'from_location' => 'Hotel',
            'to_location' => 'Stadium',
            'window_start' => now(),
            'window_end' => now()->addHour(),
            'delay_minutes' => 0,
            'status' => 'scheduled',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createJob(Event $event, Movement $movement, Team $team, array $overrides = []): JobOperation
    {
        return JobOperation::create(array_merge([
            'job_id' => 'JOB-'.Str::random(8),
            'event_id' => $event->id,
            'movement_id' => $movement->id,
            'team_id' => $team->id,
            'functional_area' => $movement->functional_area,
            'status' => 'pending',
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createCheckpoint(Event $event, JobOperation $job, array $overrides = []): JobCheckpoint
    {
        return JobCheckpoint::create(array_merge([
            'event_id' => $event->id,
            'job_id' => $job->id,
            'order' => 1,
            'name' => 'Test Checkpoint',
            'type' => 'manual',
            'state' => 'pending',
            'is_required' => true,
        ], $overrides));
    }

    protected function createUserWithRole(string $role, ?string $functionalArea = null): User
    {
        Permission::firstOrCreate(['name' => 'movements.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'movements.view-all-functional-areas', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'jobs.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'jobs.view-all-functional-areas', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'ai.use', 'guard_name' => 'web']);

        $roleModel = Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

        if ($role === 'admin') {
            $roleModel->syncPermissions(Permission::pluck('name')->all());
        } else {
            $roleModel->syncPermissions(['movements.view', 'jobs.view', 'ai.use']);
        }

        $user = User::factory()->create();
        $user->assignRole($role);

        if ($functionalArea) {
            $user->functionalAreas()->create(['functional_area' => $functionalArea]);
        }

        return $user;
    }
}
