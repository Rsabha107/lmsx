<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class EventFleetPoolSyncTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_sync_adds_the_event_providers_fleet_to_its_pool(): void
    {
        $event = $this->createEvent();
        $vehicle = Vehicle::create(['code' => 'V-LATE', 'provider_id' => $this->fixtureProvider()->id]);
        $driver = Driver::create(['name' => 'Late', 'status' => 'available', 'provider_id' => $this->fixtureProvider()->id]);
        $other = Vehicle::create(['code' => 'V-OTHER', 'provider_id' => $this->createProvider('Other')->id]);

        $this->assertFalse(Vehicle::inEventPool($event->id)->whereKey($vehicle->id)->exists());

        $this->artisan('fleet:sync-pool')->assertSuccessful();
        $this->assertFalse(Vehicle::inEventPool($event->id)->whereKey($vehicle->id)->exists(), 'dry run writes nothing');

        $this->artisan('fleet:sync-pool', ['--apply' => true])->assertSuccessful();

        $this->assertTrue(Vehicle::inEventPool($event->id)->whereKey($vehicle->id)->exists());
        $this->assertTrue(Driver::inEventPool($event->id)->whereKey($driver->id)->exists());
        $this->assertFalse(Vehicle::inEventPool($event->id)->whereKey($other->id)->exists());
    }
}
