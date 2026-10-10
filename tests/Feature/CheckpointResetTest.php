<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class CheckpointResetTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function scenario(string $jobStatus = 'in-progress'): array
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $job = $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team), $team, ['status' => $jobStatus]);
        $checkpoint = $this->createCheckpoint($event, $job, ['scheduled_at' => now()->setTime(10, 0)]);
        // A second outstanding checkpoint keeps the job from completing on its own.
        $this->createCheckpoint($event, $job, ['scheduled_at' => now()->setTime(11, 0), 'order' => 2]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return [$job, $checkpoint, $this->actingAs($admin)->withSession(['active_event_id' => $event->id])];
    }

    public function test_a_done_checkpoint_goes_back_to_not_processed(): void
    {
        [$job, $checkpoint, $as] = $this->scenario();

        $as->postJson("/jobs/checkpoint/{$checkpoint->id}/complete", ['actual_time' => '10:30', 'notes' => 'late bus'])->assertOk();
        $this->assertSame('done', $checkpoint->refresh()->state);

        $as->postJson("/jobs/checkpoint/{$checkpoint->id}/override", ['state' => 'pending', 'reason' => 'supervisor_error'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $checkpoint->refresh();
        $this->assertSame('pending', $checkpoint->state);
        $this->assertNull($checkpoint->completed_at);
        $this->assertNull($checkpoint->completed_by);
        $this->assertNull($checkpoint->notes);
        $this->assertNull($checkpoint->time_source);
        $this->assertFalse((bool) $checkpoint->was_overridden);
        $this->assertSame(0, $job->refresh()->checkpoints_completed);

        // And it can be completed again.
        $as->postJson("/jobs/checkpoint/{$checkpoint->id}/complete", ['actual_time' => '10:05'])->assertOk();
        $this->assertSame('done', $checkpoint->refresh()->state);
    }

    public function test_a_skipped_checkpoint_can_be_reset(): void
    {
        [, $checkpoint, $as] = $this->scenario();

        $as->postJson("/jobs/checkpoint/{$checkpoint->id}/override", ['state' => 'skipped', 'reason' => 'other'])->assertOk();
        $as->postJson("/jobs/checkpoint/{$checkpoint->id}/override", ['state' => 'pending', 'reason' => 'other'])->assertOk();

        $checkpoint->refresh();
        $this->assertSame('pending', $checkpoint->state);
        $this->assertNull($checkpoint->skip_reason);
        $this->assertNull($checkpoint->skipped_at);
    }

    public function test_a_checkpoint_that_was_never_processed_cannot_be_reset(): void
    {
        [, $checkpoint, $as] = $this->scenario();

        $as->postJson("/jobs/checkpoint/{$checkpoint->id}/override", ['state' => 'pending', 'reason' => 'other'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('state');
    }

    public function test_a_completed_job_keeps_its_checkpoints(): void
    {
        [$job, $checkpoint, $as] = $this->scenario();

        $as->postJson("/jobs/checkpoint/{$checkpoint->id}/complete", ['actual_time' => '10:00'])->assertOk();
        $job->checkpoints()->where('id', '!=', $checkpoint->id)->first()->update(['state' => 'skipped']);
        $as->postJson("/jobs/{$job->id}/status", ['status' => 'completed'])->assertOk();
        $this->assertSame('completed', $job->refresh()->status);

        $as->postJson("/jobs/checkpoint/{$checkpoint->id}/override", ['state' => 'pending', 'reason' => 'other'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('state');

        $this->assertSame('done', $checkpoint->refresh()->state);
    }

    public function test_a_reason_is_required(): void
    {
        [, $checkpoint, $as] = $this->scenario();
        $as->postJson("/jobs/checkpoint/{$checkpoint->id}/complete", ['actual_time' => '10:00'])->assertOk();

        $as->postJson("/jobs/checkpoint/{$checkpoint->id}/override", ['state' => 'pending'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }
}
