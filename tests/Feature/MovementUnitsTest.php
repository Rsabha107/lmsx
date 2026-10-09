<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Event;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ConflictDetectionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class MovementUnitsTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->event = $this->createEvent();
    }

    private function agency(): static
    {
        return $this->actingAs($this->createProviderUser('agency'))->withSession(['active_event_id' => $this->event->id]);
    }

    private function movement(array $attributes = [])
    {
        return $this->createMovement($this->event, $this->createPlan($this->event), $this->createTeam($this->event), $attributes + [
            'window_start' => '2026-11-27 10:00', 'window_end' => '2026-11-27 11:00',
        ]);
    }

    public function test_crew_assignment_saves_extra_vehicles_and_drivers(): void
    {
        $movement = $this->movement();
        [$bus, $truck] = [$this->createVehicle($this->event, ['code' => 'BUS-1']), $this->createVehicle($this->event, ['code' => 'TRK-1'])];
        [$lead, $second] = [$this->createDriver($this->event, ['name' => 'Lead']), $this->createDriver($this->event, ['name' => 'Second'])];

        $this->agency()->patch("/movements/{$movement->id}/crew", [
            'vehicle_id' => $bus->id, 'driver_id' => $lead->id, 'field_supervisor_id' => null,
            'units' => [['vehicle_id' => $truck->id, 'driver_id' => $second->id], ['vehicle_id' => null, 'driver_id' => null]],
        ])->assertSessionHasNoErrors();

        $units = $movement->refresh()->units;
        $this->assertCount(1, $units, 'Blank rows are dropped.');
        $this->assertSame([$truck->id, $second->id], [$units[0]->vehicle_id, $units[0]->driver_id]);
        $this->assertSame([$bus->id, $truck->id], $movement->resourceIds('vehicle_id'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Crew assigned']);
    }

    public function test_leaving_units_out_keeps_them_and_an_empty_list_clears_them(): void
    {
        $movement = $this->movement();
        $truck = Vehicle::create(['code' => 'TRK-1']);
        $movement->units()->create(['vehicle_id' => $truck->id]);

        $this->agency()->patch("/movements/{$movement->id}/crew", ['vehicle_id' => null, 'driver_id' => null, 'field_supervisor_id' => null]);
        $this->assertCount(1, $movement->refresh()->units);

        $this->agency()->patch("/movements/{$movement->id}/crew", ['vehicle_id' => null, 'driver_id' => null, 'field_supervisor_id' => null, 'units' => []]);
        $this->assertCount(0, $movement->refresh()->units);
    }

    public function test_an_extra_vehicle_is_double_booked_like_a_lead_one(): void
    {
        $truck = Vehicle::create(['code' => 'TRK-1']);
        $this->movement(['vehicle_id' => $truck->id]);
        $this->movement()->units()->create(['vehicle_id' => $truck->id]);

        $clashes = array_filter(app(ConflictDetectionService::class)->forEvent($this->event->id), fn ($c) => $c['type'] === 'Vehicle Double-Booked');

        $this->assertCount(1, $clashes);
        $this->assertStringContainsString('TRK-1', array_values($clashes)[0]['text']);
    }

    public function test_an_extra_driver_is_double_booked_like_a_lead_one(): void
    {
        $driver = Driver::create(['name' => 'Busy Driver', 'status' => 'available']);
        $this->movement()->units()->create(['driver_id' => $driver->id]);
        $this->movement()->units()->create(['driver_id' => $driver->id]);

        $clashes = array_filter(app(ConflictDetectionService::class)->forEvent($this->event->id), fn ($c) => $c['type'] === 'Driver Double-Booked');

        $this->assertCount(1, $clashes);
        $this->assertStringContainsString('Busy Driver', array_values($clashes)[0]['text']);
    }

    private function supervisor(): User
    {
        $user = User::factory()->create(['fleet_provider_id' => $this->fixtureProvider()->id]);
        $user->assignRole('ground_control');

        return $user;
    }

    public function test_crew_assignment_saves_extra_supervisors_but_not_the_lead_twice(): void
    {
        $movement = $this->movement();
        [$lead, $second] = [$this->supervisor(), $this->supervisor()];

        $this->agency()->patch("/movements/{$movement->id}/crew", [
            'vehicle_id' => null, 'driver_id' => null, 'field_supervisor_id' => $lead->id,
            'supervisors' => [$second->id, $lead->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$second->id], $movement->refresh()->extraSupervisors->pluck('id')->all());
        $this->assertSame([$lead->id, $second->id], $movement->resourceIds('field_supervisor_id'));
    }

    public function test_an_extra_supervisor_must_be_able_to_work_jobs(): void
    {
        $movement = $this->movement();
        $deskUser = User::factory()->create();

        $this->agency()->patch("/movements/{$movement->id}/crew", [
            'vehicle_id' => null, 'driver_id' => null, 'field_supervisor_id' => null, 'supervisors' => [$deskUser->id],
        ])->assertSessionHasErrors('supervisors.0');
    }

    public function test_an_extra_supervisor_is_double_booked_like_a_lead_one(): void
    {
        $busy = $this->supervisor();
        $this->movement(['field_supervisor_id' => $busy->id]);
        $this->movement()->extraSupervisors()->attach($busy->id);

        $clashes = array_filter(app(ConflictDetectionService::class)->forEvent($this->event->id), fn ($c) => $c['type'] === 'Supervisor Double-Booked');

        $this->assertCount(1, $clashes);
        $this->assertStringContainsString($busy->name, array_values($clashes)[0]['text']);
    }

    public function test_capacity_counts_every_vehicle(): void
    {
        $bus = Vehicle::create(['code' => 'BUS-1', 'capacity' => 20]);
        $van = Vehicle::create(['code' => 'VAN-1', 'capacity' => 12]);
        $movement = $this->movement(['vehicle_id' => $bus->id, 'passengers' => 30]);

        $capacityConflicts = fn () => array_filter(app(ConflictDetectionService::class)->forEvent($this->event->id), fn ($c) => $c['type'] === 'Capacity Exceeded');
        $this->assertCount(1, $capacityConflicts());

        $movement->units()->create(['vehicle_id' => $van->id]);
        $this->assertCount(0, $capacityConflicts());
    }
}
