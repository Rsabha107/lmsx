<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class JobDeleteTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = $this->createEvent();
    }

    /** @param  array<int, string>  $permissions */
    private function actingWith(array $permissions): static
    {
        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $role = Role::firstOrCreate(['name' => 'r'.md5(implode(',', $permissions)), 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        $user = User::factory()->create();
        $user->assignRole($role);
        $user->events()->attach($this->event);

        return $this->actingAs($user)->withSession(['active_event_id' => $this->event->id]);
    }

    private function planner(): static
    {
        return $this->actingWith(['jobs.view', 'jobs.view-all-functional-areas', 'plans.manage']);
    }

    private function jobWithMovement(): array
    {
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $movement = $this->createMovement($this->event, $plan, $team);
        $job = $this->createJob($this->event, $movement, $team);
        $movement->update(['job_id' => $job->job_id, 'job_generated_at' => now()]);
        $this->createCheckpoint($this->event, $job);

        return [$job, $movement];
    }

    public function test_deleting_jobs_removes_checkpoints_and_frees_movements(): void
    {
        [$job1, $movement1] = $this->jobWithMovement();
        [$job2, $movement2] = $this->jobWithMovement();
        [$kept] = $this->jobWithMovement();

        $this->planner()->delete('/jobs', ['ids' => [$job1->id, $job2->id]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('jobs_operations', ['id' => $job1->id]);
        $this->assertDatabaseMissing('jobs_operations', ['id' => $job2->id]);
        $this->assertDatabaseHas('jobs_operations', ['id' => $kept->id]);
        $this->assertSame(0, JobCheckpoint::whereIn('job_id', [$job1->id, $job2->id])->count());
        $this->assertNull($movement1->refresh()->job_id);
        $this->assertNull($movement2->refresh()->job_generated_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Job deleted']);
    }

    public function test_started_job_needs_its_id_typed_to_delete(): void
    {
        [$job] = $this->jobWithMovement();
        $job->update(['status' => 'completed']);

        $this->planner()->delete('/jobs', ['ids' => [$job->id], 'confirm' => 'nope'])
            ->assertSessionHasErrors('confirm');
        $this->assertDatabaseHas('jobs_operations', ['id' => $job->id]);

        $this->planner()->delete('/jobs', ['ids' => [$job->id], 'confirm' => $job->job_id])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('jobs_operations', ['id' => $job->id]);
    }

    public function test_bulk_delete_with_a_started_job_needs_delete_typed(): void
    {
        [$pending] = $this->jobWithMovement();
        [$started] = $this->jobWithMovement();
        $started->update(['status' => 'in-progress']);

        $this->planner()->delete('/jobs', ['ids' => [$pending->id, $started->id]])
            ->assertSessionHasErrors('confirm');

        $this->planner()->delete('/jobs', ['ids' => [$pending->id, $started->id], 'confirm' => 'DELETE'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0, JobOperation::whereKey([$pending->id, $started->id])->count());
    }

    public function test_deleting_needs_plans_manage(): void
    {
        [$job] = $this->jobWithMovement();

        $this->actingWith(['jobs.view', 'jobs.view-all-functional-areas'])
            ->delete('/jobs', ['ids' => [$job->id]])
            ->assertForbidden();

        $this->assertDatabaseHas('jobs_operations', ['id' => $job->id]);
    }

    public function test_jobs_of_another_event_cannot_be_deleted(): void
    {
        $other = $this->createEvent();
        $team = $this->createTeam($other);
        $movement = $this->createMovement($other, $this->createPlan($other), $team);
        $job = $this->createJob($other, $movement, $team);

        $this->planner()->delete('/jobs', ['ids' => [$job->id]])->assertForbidden();

        $this->assertTrue(JobOperation::whereKey($job->id)->exists());
    }
}
