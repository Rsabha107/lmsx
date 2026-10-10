<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Event;
use App\Models\FleetProvider;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\NotificationFeedService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class AgencyCrewAssignmentTest extends TestCase
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

    private function supervisor(): User
    {
        return $this->createProviderUser('ground_control');
    }

    public function test_the_agency_sets_the_crew_on_the_movement_and_its_job(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team);
        $job = $this->createJob($event, $movement, $team);

        $vehicle = $this->createVehicle($event, ['code' => 'BUS-01']);
        $driver = $this->createDriver($event, ['name' => 'Agency Driver']);
        $supervisor = $this->supervisor();

        $this->agency($event)
            ->patch("/movements/{$movement->id}/crew", [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $driver->id,
                'field_supervisor_id' => $supervisor->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $movement->refresh();
        $this->assertSame([$vehicle->id, $driver->id, $supervisor->id], [$movement->vehicle_id, $movement->driver_id, $movement->field_supervisor_id]);

        $job->refresh();
        $this->assertSame([$vehicle->id, $driver->id, $supervisor->id], [$job->vehicle_id, $job->driver_id, $job->supervisor_id]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'Crew assigned', 'user_role' => 'agency']);

        $feed = app(NotificationFeedService::class)->recent($event->id);
        $this->assertContains('Agency crew change: '.$movement->code.' · '.$job->job_id, array_column($feed, 'title'));
    }

    public function test_the_agency_can_clear_a_role(): void
    {
        $event = $this->createEvent();
        $driver = $this->createDriver($event, ['name' => 'Old Driver']);
        $movement = $this->createMovement($event, $this->createPlan($event), $this->createTeam($event), ['driver_id' => $driver->id]);

        $this->agency($event)
            ->patch("/movements/{$movement->id}/crew", ['vehicle_id' => null, 'driver_id' => null, 'field_supervisor_id' => null])
            ->assertRedirect();

        $this->assertNull($movement->refresh()->driver_id);
    }

    public function test_all_three_fields_must_be_sent(): void
    {
        $event = $this->createEvent();
        $driver = $this->createDriver($event, ['name' => 'Kept Driver']);
        $movement = $this->createMovement($event, $this->createPlan($event), $this->createTeam($event), ['driver_id' => $driver->id]);

        $this->agency($event)
            ->patch("/movements/{$movement->id}/crew", ['vehicle_id' => null])
            ->assertSessionHasErrors(['driver_id', 'field_supervisor_id']);

        $this->assertSame($driver->id, $movement->refresh()->driver_id);
    }

    public function test_the_supervisor_must_be_able_to_work_jobs(): void
    {
        $event = $this->createEvent();
        $movement = $this->createMovement($event, $this->createPlan($event), $this->createTeam($event));
        $notASupervisor = User::factory()->create();

        $this->agency($event)
            ->patch("/movements/{$movement->id}/crew", ['vehicle_id' => null, 'driver_id' => null, 'field_supervisor_id' => $notASupervisor->id])
            ->assertSessionHasErrors('field_supervisor_id');
    }

    public function test_a_movement_in_another_event_is_refused(): void
    {
        $movement = $this->createMovement($other = $this->createEvent(), $this->createPlan($other), $this->createTeam($other));

        $this->agency($this->createEvent())
            ->patch("/movements/{$movement->id}/crew", ['vehicle_id' => null, 'driver_id' => null, 'field_supervisor_id' => null])
            ->assertForbidden();
    }

    public function test_the_day_allocation_sheet_lists_that_days_crew(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $vehicle = $this->createVehicle($event, ['code' => 'BUS-01', 'plate_number' => 'QA-1234']);
        $driver = $this->createDriver($event, ['name' => 'Sheet Driver']);
        $supervisor = $this->supervisor();
        $movement = $this->createMovement($event, $this->createPlan($event), $team, [
            'window_start' => '2026-10-14 10:00:00',
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'field_supervisor_id' => $supervisor->id,
        ]);
        $this->createJob($event, $movement, $team, ['job_id' => 'JOB-SHEET-1']);
        $this->createMovement($event, $this->createPlan($event), $team, ['code' => 'MV-OTHERDAY', 'window_start' => '2026-10-15 10:00:00']);

        $response = $this->agency($event)->get('/crew-assignment/export?date=2026-10-14')->assertOk();

        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->capture($response))->getActiveSheet();
        $this->assertSame('JOB-SHEET-1', $sheet->getCell('A4')->getValue());
        $this->assertSame('QA-1234', $sheet->getCell('M4')->getValue());
        $this->assertSame('Sheet Driver', $sheet->getCell('J4')->getValue());
        $this->assertNull($sheet->getCell('G4')->getValue());
        $this->assertNull($sheet->getCell('A5')->getValue());
    }

    private function capture($response): string
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());

        return $path;
    }

    public function test_the_agency_can_read_the_console(): void
    {
        $event = $this->createEvent();

        $this->agency($event)->get('/crew-assignment')->assertOk();
        $this->agency($event)->get('/plans')->assertOk();
        $this->agency($event)->get('/jobs')->assertOk();
    }

    public function test_the_agency_cannot_change_anything_else(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team);
        $job = $this->createJob($event, $movement, $team);
        $checkpoint = $this->createCheckpoint($event, $job);

        $this->agency($event)->put("/movements/{$movement->id}", ['notes' => 'x'])->assertForbidden();
        $this->agency($event)->postJson("/jobs/{$job->id}/status", ['status' => 'dispatched'])->assertForbidden();
        $this->agency($event)->postJson("/jobs/checkpoint/{$checkpoint->id}/complete")->assertForbidden();
        $this->agency($event)->post('/venues', ['name' => 'x'])->assertForbidden();
        $this->agency($event)->post('/email/send')->assertForbidden();

        $this->assertSame('pending', $job->refresh()->status);
    }

    public function test_an_admin_must_pick_a_provider_for_a_vehicle(): void
    {
        $event = $this->createEvent();
        $admin = $this->actingAs($this->createUserWithRole('admin'))->withSession(['active_event_id' => $event->id]);
        $payload = ['code' => 'AD-01', 'vehicle_type' => 'Bus', 'status' => 'available'];

        $admin->post('/fleet/vehicles', $payload)->assertSessionHasErrors('provider_id');
        $this->assertDatabaseMissing('vehicles', ['code' => 'AD-01']);

        $admin->post('/fleet/vehicles', $payload + ['provider_id' => $this->fixtureProvider()->id])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vehicles', ['code' => 'AD-01']);
    }

    public function test_bulk_delete_skips_vehicles_still_on_a_movement(): void
    {
        $event = $this->createEvent();
        $used = $this->createVehicle($event, ['code' => 'USED-1']);
        $free = $this->createVehicle($event, ['code' => 'FREE-1']);
        $this->createMovement($event, $this->createPlan($event), $this->createTeam($event), ['vehicle_id' => $used->id]);

        $this->agency($event)
            ->post('/fleet/bulk-delete', ['type' => 'vehicle', 'ids' => [$used->id, $free->id]])
            ->assertSessionHas('error');

        $this->assertModelExists($used);
        $this->assertModelMissing($free);
    }

    public function test_only_an_admin_can_bulk_assign_a_provider(): void
    {
        $event = $this->createEvent();
        $vehicle = $this->createVehicle($event, ['code' => 'MOVE-1']);
        $other = FleetProvider::create(['code' => 'OTH', 'name' => 'Other Co', 'status' => 'active']);
        $payload = ['type' => 'vehicle', 'ids' => [$vehicle->id], 'provider_id' => $other->id];

        $this->agency($event)->post('/fleet/bulk-provider', $payload)->assertForbidden();
        $this->assertNotSame($other->id, $vehicle->refresh()->provider_id);

        $this->actingAs($this->createUserWithRole('admin'))->withSession(['active_event_id' => $event->id])
            ->post('/fleet/bulk-provider', $payload)->assertSessionHas('success');
        $this->assertSame($other->id, $vehicle->refresh()->provider_id);
    }

    public function test_the_agency_manages_vehicles_and_drivers(): void
    {
        $event = $this->createEvent();

        $this->agency($event)->post('/fleet/vehicles', ['code' => 'AG-01', 'vehicle_type' => 'Bus', 'status' => 'available'])->assertSessionHasNoErrors();
        $vehicle = Vehicle::where('code', 'AG-01')->firstOrFail();
        $this->agency($event)->put("/fleet/vehicles/{$vehicle->id}", ['code' => 'AG-02', 'vehicle_type' => 'Bus', 'status' => 'standby'])->assertSessionHasNoErrors();
        $this->assertSame('AG-02', $vehicle->refresh()->code);
        $this->agency($event)->delete("/fleet/vehicles/{$vehicle->id}")->assertRedirect();
        $this->assertModelMissing($vehicle);

        $this->agency($event)->post('/fleet/drivers', ['name' => 'New Driver', 'status' => 'available'])->assertSessionHasNoErrors();
        $driver = Driver::where('name', 'New Driver')->firstOrFail();
        $this->agency($event)->put("/fleet/drivers/{$driver->id}", ['name' => 'Renamed Driver', 'status' => 'off'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed Driver', $driver->refresh()->name);
        $this->agency($event)->delete("/fleet/drivers/{$driver->id}")->assertRedirect();
        $this->assertModelMissing($driver);

        $this->agency($event)->post('/fleet/providers', ['code' => 'AGP', 'name' => 'Agency Provider', 'status' => 'active'])->assertForbidden();

        $own = $this->fixtureProvider();
        $this->agency($event)->put("/fleet/providers/{$own->id}", ['code' => 'GWC', 'name' => 'Renamed Provider', 'status' => 'standby'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed Provider', $own->refresh()->name);
        $this->agency($event)->delete("/fleet/providers/{$own->id}")->assertForbidden();
    }

    public function test_the_agency_cannot_manage_other_fleet_data(): void
    {
        $event = $this->createEvent();

        $this->agency($event)->post('/contacts', ['name' => 'x'])->assertForbidden();
        $this->agency($event)->post('/airports', ['name' => 'x'])->assertForbidden();
    }

    public function test_an_inertia_write_is_refused_with_a_flagged_json_403(): void
    {
        $this->agency($this->createEvent())
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/venues', ['name' => 'x'])
            ->assertForbidden()
            ->assertHeader('X-Access-Restricted', '1')
            ->assertJsonStructure(['message']);
    }

    public function test_the_mobile_api_is_closed_to_the_agency(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $job = $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team), $team);

        $user = $this->createProviderUser('agency');
        $user->events()->attach($event);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/mobile/jobs/{$job->id}?event_id={$event->id}")
            ->assertForbidden();
    }
}
