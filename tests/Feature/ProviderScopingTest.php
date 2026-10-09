<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Movement;
use App\Models\User;
use App\Services\ConflictDetectionService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class ProviderScopingTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private const NO_CREW = ['vehicle_id' => null, 'driver_id' => null, 'field_supervisor_id' => null];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function agency($event, $provider = null): static
    {
        $user = $this->createProviderUser('agency', $provider);
        $user->events()->attach($event);

        return $this->actingAs($user)->withSession(['active_event_id' => $event->id]);
    }

    private function movementOn($event, string $start, array $attrs = []): Movement
    {
        return $this->createMovement($event, $this->createPlan($event), $this->createTeam($event), $attrs + [
            'window_start' => $start, 'window_end' => date('Y-m-d H:i', strtotime($start) + 3600),
        ]);
    }

    public function test_a_provider_only_sees_and_edits_its_own_movements(): void
    {
        $event = $this->createEvent();
        $mine = $this->movementOn($event, '2026-10-14 10:00');
        $theirs = $this->movementOn($event, '2026-10-14 11:00', ['fleet_provider_id' => $this->createProvider('Other')->id]);

        $this->agency($event)->get('/crew-assignment?date=2026-10-14')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('movements', 1)->where('movements.0.id', $mine->id));

        $this->agency($event)->patch("/movements/{$theirs->id}/crew", self::NO_CREW)->assertNotFound();
    }

    public function test_a_new_movement_starts_with_the_events_provider(): void
    {
        $event = $this->createEvent();

        $this->assertSame($this->fixtureProvider()->id, $this->movementOn($event, '2026-10-14 10:00')->fleet_provider_id);
    }

    public function test_only_the_providers_own_fleet_in_the_event_can_be_assigned(): void
    {
        $event = $this->createEvent();
        $movement = $this->movementOn($event, '2026-10-14 10:00');
        $other = $this->createProvider('Other');

        $foreign = $this->createVehicle($event, ['code' => 'OTH-1', 'provider_id' => $other->id]);
        $outOfPool = Driver::create(['name' => 'Benched', 'status' => 'available', 'provider_id' => $this->fixtureProvider()->id]);
        $own = $this->createVehicle($event, ['code' => 'OWN-1']);

        $this->agency($event)->patch("/movements/{$movement->id}/crew", ['vehicle_id' => $foreign->id] + self::NO_CREW)
            ->assertSessionHasErrors('vehicle_id');
        $this->agency($event)->patch("/movements/{$movement->id}/crew", ['driver_id' => $outOfPool->id] + self::NO_CREW)
            ->assertSessionHasErrors('driver_id');
        $this->agency($event)->patch("/movements/{$movement->id}/crew", ['vehicle_id' => $own->id] + self::NO_CREW)
            ->assertSessionHasNoErrors();
    }

    public function test_supervisors_must_belong_to_the_movements_provider(): void
    {
        $event = $this->createEvent();
        $movement = $this->movementOn($event, '2026-10-14 10:00');
        $outsider = $this->createProviderUser('ground_control', $this->createProvider('Other'));
        $insider = $this->createProviderUser('ground_control');

        $this->agency($event)->patch("/movements/{$movement->id}/crew", ['field_supervisor_id' => $outsider->id] + self::NO_CREW)
            ->assertSessionHasErrors('field_supervisor_id');
        $this->agency($event)->patch("/movements/{$movement->id}/crew", ['field_supervisor_id' => $insider->id] + self::NO_CREW)
            ->assertSessionHasNoErrors();
    }

    public function test_the_day_sheet_only_lists_the_providers_movements(): void
    {
        $event = $this->createEvent();
        $this->movementOn($event, '2026-10-14 10:00');
        $this->movementOn($event, '2026-10-14 11:00', ['fleet_provider_id' => $this->createProvider('Other')->id]);

        $response = $this->agency($event)->get('/crew-assignment/export?date=2026-10-14')->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();

        $this->assertNotNull($sheet->getCell('A4')->getValue());
        $this->assertNull($sheet->getCell('A5')->getValue());
    }

    public function test_a_provider_only_sees_its_own_fleet_and_can_offer_it_to_the_event(): void
    {
        $event = $this->createEvent();
        $other = $this->createProvider('Other');
        $mine = Driver::create(['name' => 'Mine', 'status' => 'available', 'provider_id' => $this->fixtureProvider()->id]);
        $theirs = Driver::create(['name' => 'Theirs', 'status' => 'available', 'provider_id' => $other->id]);

        $this->agency($event)->get('/fleet')->assertInertia(fn (AssertableInertia $page) => $page
            ->has('drivers', 1)->where('drivers.0.id', $mine->id)->has('providers', 1));

        $this->agency($event)->post('/fleet/pool', ['type' => 'driver', 'id' => $mine->id, 'in_pool' => true])->assertRedirect();
        $this->assertDatabaseHas('event_driver', ['event_id' => $event->id, 'driver_id' => $mine->id]);

        $this->agency($event)->post('/fleet/pool', ['type' => 'driver', 'id' => $theirs->id, 'in_pool' => true])->assertNotFound();

        $this->agency($event)->post('/fleet/pool', ['type' => 'driver', 'id' => $mine->id, 'in_pool' => false])->assertRedirect();
        $this->assertDatabaseMissing('event_driver', ['driver_id' => $mine->id]);
    }

    public function test_an_admin_can_hand_a_movement_to_another_provider(): void
    {
        $event = $this->createEvent();
        $vehicle = $this->createVehicle($event);
        $movement = $this->movementOn($event, '2026-10-14 10:00', ['vehicle_id' => $vehicle->id]);
        $other = $this->createProvider('Other');
        $admin = $this->createUserWithRole('admin');
        $admin->events()->attach($event);

        $this->actingAs($admin)->withSession(['active_event_id' => $event->id])
            ->patch("/movements/{$movement->id}/provider", ['fleet_provider_id' => $other->id])
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertSame($other->id, $movement->refresh()->fleet_provider_id);
    }

    public function test_a_shared_driver_clashes_across_overlapping_events(): void
    {
        $eventA = $this->createEvent();
        $eventB = $this->createEvent();
        $driver = $this->createDriver($eventA);
        $driver->events()->attach($eventB->id);

        $this->movementOn($eventA, '2026-11-27 12:00', ['driver_id' => $driver->id, 'code' => 'A-1']);
        $this->movementOn($eventB, '2026-11-27 12:30', ['driver_id' => $driver->id, 'code' => 'B-1']);

        $clashes = fn ($eventId) => array_values(array_filter(
            app(ConflictDetectionService::class)->forEvent($eventId),
            fn ($c) => $c['type'] === 'Driver Double-Booked',
        ));

        $this->assertCount(1, $clashes($eventA->id));
        $this->assertStringContainsString('B-1', $clashes($eventA->id)[0]['text']);
        $this->assertCount(1, $clashes($eventB->id));
    }

    public function test_the_same_driver_in_another_event_at_a_different_time_is_fine(): void
    {
        $eventA = $this->createEvent();
        $eventB = $this->createEvent();
        $driver = $this->createDriver($eventA);

        $this->movementOn($eventA, '2026-11-27 08:00', ['driver_id' => $driver->id]);
        $this->movementOn($eventB, '2026-11-27 20:00', ['driver_id' => $driver->id]);

        $this->assertSame([], array_values(array_filter(
            app(ConflictDetectionService::class)->forEvent($eventA->id),
            fn ($c) => $c['type'] === 'Driver Double-Booked',
        )));
    }
}
