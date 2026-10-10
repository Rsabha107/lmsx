<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Event;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class OverrideCrewChangeTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    /** The policy requires the jobs.override grant and the job's event to be the active one. */
    private function actingAsOverrider(Event $event, ?User $user = null): static
    {
        Permission::firstOrCreate(['name' => 'jobs.override', 'guard_name' => 'web']);
        $user ??= $this->createUserWithRole('admin');
        $user->givePermissionTo('jobs.override');

        return $this->actingAs($user)->withSession(['active_event_id' => $event->id]);
    }

    public function test_an_override_can_swap_the_driver_and_supervisor_on_the_job_and_its_movement(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $plan = $this->createPlan($event);
        $admin = $this->createUserWithRole('admin');

        $oldDriver = $this->createDriver($event, ['name' => 'Old Driver']);
        $newDriver = $this->createDriver($event, ['name' => 'New Driver']);
        Role::findOrCreate('ground_control', 'web');
        $newSupervisor = $this->createProviderUser('ground_control');

        $movement = $this->createMovement($event, $plan, $team, ['driver_id' => $oldDriver->id]);
        $job = $this->createJob($event, $movement, $team, ['driver_id' => $oldDriver->id]);
        $movement->update(['job_id' => $job->job_id]);
        $checkpoint = $this->createCheckpoint($event, $job);

        $this->actingAsOverrider($event, $admin)
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/override", [
                'state' => 'skipped',
                'reason' => 'operational_change',
                'driver_id' => $newDriver->id,
                'supervisor_id' => $newSupervisor->id,
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $job->refresh();
        $this->assertSame($newDriver->id, $job->driver_id);
        $this->assertSame($newSupervisor->id, $job->supervisor_id);

        $movement->refresh();
        $this->assertSame($newDriver->id, $movement->driver_id);
        $this->assertSame($newSupervisor->id, $movement->field_supervisor_id);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'Job crew changed',
            'target' => $job->job_id,
        ]);
    }

    public function test_an_override_refuses_a_supervisor_who_cannot_work_jobs(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team);
        $job = $this->createJob($event, $movement, $team);
        $movement->update(['job_id' => $job->job_id]);
        $checkpoint = $this->createCheckpoint($event, $job);
        $notASupervisor = User::factory()->create(['fleet_provider_id' => $this->fixtureProvider()->id]);

        $this->actingAsOverrider($event)
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/override", [
                'state' => 'skipped',
                'reason' => 'operational_change',
                'supervisor_id' => $notASupervisor->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('supervisor_id');

        $this->assertNull($job->refresh()->supervisor_id);
    }

    public function test_a_vehicle_can_be_swapped_on_the_job_and_its_movement(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $old = $this->createVehicle($event, ['code' => 'BUS-01', 'plate_number' => 'P-1', 'vehicle_type' => 'bus', 'capacity' => 50]);
        $new = $this->createVehicle($event, ['code' => 'BUS-02', 'plate_number' => 'P-2', 'vehicle_type' => 'bus', 'capacity' => 50]);

        $movement = $this->createMovement($event, $this->createPlan($event), $team, ['vehicle_id' => $old->id]);
        $job = $this->createJob($event, $movement, $team, ['vehicle_id' => $old->id]);
        $checkpoint = $this->createCheckpoint($event, $job);

        $this->actingAsOverrider($event)
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/override", ['vehicle_id' => $new->id])
            ->assertOk();

        $this->assertSame($new->id, $job->refresh()->vehicle_id);
        $this->assertSame($new->id, $movement->refresh()->vehicle_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Job crew changed', 'meta' => 'Vehicle BUS-01 → BUS-02 · no reason given']);
    }

    public function test_an_override_without_crew_fields_leaves_the_crew_alone(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $plan = $this->createPlan($event);
        $driver = $this->createDriver($event, ['name' => 'Kept Driver']);

        $movement = $this->createMovement($event, $plan, $team, ['driver_id' => $driver->id]);
        $job = $this->createJob($event, $movement, $team, ['driver_id' => $driver->id]);
        $checkpoint = $this->createCheckpoint($event, $job);

        $this->actingAsOverrider($event)
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/override", [
                'state' => 'skipped',
                'reason' => 'other',
            ])
            ->assertOk();

        $this->assertSame($driver->id, $job->refresh()->driver_id);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Job crew changed']);
    }

    public function test_a_crew_change_without_a_new_state_needs_no_reason_and_leaves_the_checkpoint_alone(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $plan = $this->createPlan($event);
        $oldDriver = $this->createDriver($event, ['name' => 'Old Driver']);
        $newDriver = $this->createDriver($event, ['name' => 'New Driver']);

        $movement = $this->createMovement($event, $plan, $team, ['driver_id' => $oldDriver->id]);
        $job = $this->createJob($event, $movement, $team, ['driver_id' => $oldDriver->id]);
        $checkpoint = $this->createCheckpoint($event, $job);

        $this->actingAsOverrider($event)
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/override", ['driver_id' => $newDriver->id])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame($newDriver->id, $job->refresh()->driver_id);
        $this->assertSame($newDriver->id, $movement->refresh()->driver_id);

        $checkpoint->refresh();
        $this->assertSame('pending', $checkpoint->state);
        $this->assertFalse((bool) $checkpoint->was_overridden);

        $this->assertDatabaseHas('audit_logs', ['action' => 'Job crew changed']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'Checkpoint overridden']);
    }

    public function test_a_new_state_still_requires_a_reason(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $plan = $this->createPlan($event);
        $movement = $this->createMovement($event, $plan, $team);
        $checkpoint = $this->createCheckpoint($event, $this->createJob($event, $movement, $team));

        $this->actingAsOverrider($event)
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/override", ['state' => 'skipped'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertSame('pending', $checkpoint->refresh()->state);
    }

    public function test_no_state_and_no_crew_change_is_rejected(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $plan = $this->createPlan($event);
        $driver = Driver::create(['name' => 'Same Driver', 'status' => 'available']);
        $movement = $this->createMovement($event, $plan, $team, ['driver_id' => $driver->id]);
        $job = $this->createJob($event, $movement, $team, ['driver_id' => $driver->id]);
        $checkpoint = $this->createCheckpoint($event, $job);

        $this->actingAsOverrider($event)
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/override", ['driver_id' => $driver->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('state');
    }
}
