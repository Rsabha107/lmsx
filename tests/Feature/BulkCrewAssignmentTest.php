<?php

namespace Tests\Feature;

use App\Models\Event;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class BulkCrewAssignmentTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function agency(Event $event): static
    {
        return $this->actingAs($this->createProviderUser('agency'))->withSession(['active_event_id' => $event->id]);
    }

    private function movement(Event $event, array $attrs = [])
    {
        return $this->createMovement($event, $this->createPlan($event), $this->createTeam($event), $attrs);
    }

    public function test_several_movements_are_saved_in_one_request_with_one_message(): void
    {
        $event = $this->createEvent();
        $a = $this->movement($event, ['window_start' => '2026-10-14 08:00', 'window_end' => '2026-10-14 09:00']);
        $b = $this->movement($event, ['window_start' => '2026-10-14 12:00', 'window_end' => '2026-10-14 13:00']);
        $vehicle = $this->createVehicle($event);
        $driver = $this->createDriver($event);
        $supervisor = $this->createProviderUser('ground_control');

        $this->agency($event)->patch('/movements/crew', ['movements' => [
            ['id' => $a->id, 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'field_supervisor_id' => $supervisor->id],
            ['id' => $b->id, 'vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'field_supervisor_id' => null],
        ]])->assertRedirect()->assertSessionHas('success', 'Crew updated for 2 movements.');

        $this->assertSame($vehicle->id, $a->refresh()->vehicle_id);
        $this->assertSame($supervisor->id, $a->field_supervisor_id);
        $this->assertSame($driver->id, $b->refresh()->driver_id);
    }

    public function test_a_clash_is_one_warning_and_an_invalid_row_does_not_block_the_others(): void
    {
        $event = $this->createEvent();
        $a = $this->movement($event, ['window_start' => '2026-10-14 08:00', 'window_end' => '2026-10-14 09:00']);
        $b = $this->movement($event, ['window_start' => '2026-10-14 08:30', 'window_end' => '2026-10-14 09:30']);
        $driver = $this->createDriver($event);
        $outsider = $this->createDriver($event, ['provider_id' => $this->createProvider('Other')->id]);

        $response = $this->agency($event)->patch('/movements/crew', ['movements' => [
            ['id' => $a->id, 'vehicle_id' => null, 'driver_id' => $driver->id, 'field_supervisor_id' => null],
            ['id' => $b->id, 'vehicle_id' => null, 'driver_id' => $driver->id, 'field_supervisor_id' => null],
        ]]);
        $response->assertSessionHas('warning');
        $this->assertSame($driver->id, $b->refresh()->driver_id);

        $c = $this->movement($event, ['window_start' => '2026-10-15 08:00', 'window_end' => '2026-10-15 09:00']);
        $d = $this->movement($event, ['window_start' => '2026-10-15 12:00', 'window_end' => '2026-10-15 13:00']);

        $this->agency($event)->patch('/movements/crew', ['movements' => [
            ['id' => $c->id, 'vehicle_id' => null, 'driver_id' => $outsider->id, 'field_supervisor_id' => null],
            ['id' => $d->id, 'vehicle_id' => null, 'driver_id' => $driver->id, 'field_supervisor_id' => null],
        ]])->assertSessionHasErrors('crew');

        $this->assertNull($c->refresh()->driver_id);
        $this->assertSame($driver->id, $d->refresh()->driver_id);
    }

    public function test_another_events_movement_is_refused(): void
    {
        $other = $this->createEvent();
        $foreign = $this->movement($other);

        $this->agency($this->createEvent())->patch('/movements/crew', ['movements' => [
            ['id' => $foreign->id, 'vehicle_id' => null, 'driver_id' => null, 'field_supervisor_id' => null],
        ]])->assertSessionHasErrors('crew');
    }
}
