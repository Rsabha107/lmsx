<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Event;
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
        $user = User::factory()->create();
        $user->assignRole('agency');

        return $this->actingAs($user)->withSession(['active_event_id' => $event->id]);
    }

    private function supervisor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('ground_control');

        return $user;
    }

    public function test_the_agency_sets_the_crew_on_the_movement_and_its_job(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team);
        $job = $this->createJob($event, $movement, $team);

        $vehicle = Vehicle::create(['code' => 'BUS-01']);
        $driver = Driver::create(['name' => 'Agency Driver', 'status' => 'available']);
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
        $driver = Driver::create(['name' => 'Old Driver', 'status' => 'available']);
        $movement = $this->createMovement($event, $this->createPlan($event), $this->createTeam($event), ['driver_id' => $driver->id]);

        $this->agency($event)
            ->patch("/movements/{$movement->id}/crew", ['vehicle_id' => null, 'driver_id' => null, 'field_supervisor_id' => null])
            ->assertRedirect();

        $this->assertNull($movement->refresh()->driver_id);
    }

    public function test_all_three_fields_must_be_sent(): void
    {
        $event = $this->createEvent();
        $driver = Driver::create(['name' => 'Kept Driver', 'status' => 'available']);
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
        $this->agency($event)->post("/jobs/{$job->id}/dispatch")->assertForbidden();
        $this->agency($event)->post('/venues', ['name' => 'x'])->assertForbidden();
        $this->agency($event)->post('/email/send')->assertForbidden();

        $this->assertSame('pending', $job->refresh()->status);
    }

    public function test_the_mobile_api_is_closed_to_the_agency(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $job = $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team), $team);

        $user = User::factory()->create();
        $user->assignRole('agency');
        $user->events()->attach($event);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/mobile/jobs/{$job->id}?event_id={$event->id}")
            ->assertForbidden();
    }
}
