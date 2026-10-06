<?php

namespace Tests\Feature;

use App\Models\TeamFlight;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class JobDetailTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_it_shows_the_jobs_real_route_and_checkpoint_times(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, [
            'from_location' => 'Airport', 'to_location' => 'Hotel', 'passengers' => 32,
            'window_start' => '2026-11-24 08:00', 'window_end' => '2026-11-24 10:00',
        ]);
        $job = $this->createJob($event, $movement, $team);
        $this->createCheckpoint($event, $job, ['order' => 1, 'name' => 'Pickup', 'state' => 'done',
            'scheduled_at' => '2026-11-24 08:05', 'completed_at' => '2026-11-24 08:12']);
        $this->createCheckpoint($event, $job, ['order' => 2, 'name' => 'Drop', 'scheduled_at' => '2026-11-24 09:40']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->withSession(['active_event_id' => $event->id])
            ->get("/job/{$job->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('JobDetail')
                ->where('job.from', 'Airport')
                ->where('job.to', 'Hotel')
                ->where('job.pax', 32)
                ->where('job.dep', '08:05')
                ->where('job.dep_actual', '08:12')
                ->where('job.dep_delay', 7)
                ->where('job.arr', '09:40')
                ->where('job.can_override', true)
                ->where('job.can_change_status', true)
                // The Jobs Queue row and crew pools feed the shared override modal.
                ->where('queueJob.db_id', $job->id)
                ->where('queueJob.checkpoints.1.scheduled_at', '09:40')
                ->has('drivers')
                ->has('vehicles')
                ->where('checkpoints.0.time', '08:05')
                ->where('checkpoints.0.status', 'done')
                ->where('checkpoints.1.time', '09:40')
                ->where('checkpoints.1.status', 'active'));
    }

    public function test_the_override_gets_the_arrival_flight(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $flight = TeamFlight::create([
            'event_id' => $event->id, 'team_id' => $team->id, 'direction' => 'arrival',
            'flight_number' => 'QR100', 'scheduled_at' => '2026-11-08 04:00:00', 'terminal' => 'T1',
        ]);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, [
            'kind' => 'arrival', 'flight_id' => $flight->id, 'window_start' => '2026-11-08 03:00:00',
        ]);
        $job = $this->createJob($event, $movement, $team);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->withSession(['active_event_id' => $event->id])
            ->get("/job/{$job->id}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('queueJob.kind', 'arrival')
                ->where('queueJob.flight.direction', 'arrival')
                ->where('queueJob.flight.flight_number', 'QR100')
                ->where('queueJob.flight.scheduled_local', '2026-11-08T04:00'));
    }

    public function test_a_job_from_another_event_is_refused(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $job = $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team), $team);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->withSession(['active_event_id' => $this->createEvent()->id])
            ->get("/job/{$job->id}")
            ->assertForbidden();
    }
}
