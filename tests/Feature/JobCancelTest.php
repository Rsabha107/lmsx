<?php

namespace Tests\Feature;

use App\Models\JobOperation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class JobCancelTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_cancelling_keeps_the_job_and_locks_its_status(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team);
        $job = $this->createJob($event, $movement, $team);
        $checkpoint = $this->createCheckpoint($event, $job);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $as = $this->actingAs($admin)->withSession(['active_event_id' => $event->id]);

        $as->postJson("/jobs/{$job->id}/status", ['status' => 'cancelled'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('cancelled', JobOperation::find($job->id)?->status);
        $this->assertModelExists($checkpoint);

        $as->postJson("/jobs/{$job->id}/status", ['status' => 'pending'])->assertStatus(422);

        $as->postJson("/jobs/checkpoint/{$checkpoint->id}/override", ['state' => 'done', 'reason' => 'x'])
            ->assertForbidden();
    }

    public function test_reinstating_returns_the_job_to_its_status_before_the_cancel(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $job = $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team), $team, ['status' => 'dispatched']);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $as = $this->actingAs($admin)->withSession(['active_event_id' => $event->id]);

        $as->postJson("/jobs/{$job->id}/status", ['status' => 'cancelled'])->assertOk();
        $this->assertSame('dispatched', $job->refresh()->cancelled_from);

        $as->postJson("/jobs/{$job->id}/reinstate")->assertOk()->assertJson(['status' => 'dispatched']);
        $this->assertSame('dispatched', $job->refresh()->status);
        $this->assertNull($job->cancelled_from);

        // Only a cancelled job can be reinstated.
        $as->postJson("/jobs/{$job->id}/reinstate")->assertStatus(422);
    }

    public function test_a_job_cancelled_before_the_status_was_recorded_goes_back_by_its_timestamps(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $job = $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team), $team, [
            'status' => 'cancelled', 'started_at' => now(),
        ]);

        $this->assertSame('in-progress', $job->reinstateStatus());
    }
}
