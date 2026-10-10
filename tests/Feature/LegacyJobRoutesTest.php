<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class LegacyJobRoutesTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private function fieldSupervisor(Event $event): User
    {
        foreach (['jobs.view', 'jobs.view-all-functional-areas'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'ground_control', 'guard_name' => 'web'])
            ->syncPermissions(['jobs.view', 'jobs.view-all-functional-areas']);

        $user = User::factory()->create();
        $user->assignRole('ground_control');
        $user->events()->attach($event->id);

        return $user;
    }

    public function test_the_old_web_job_actions_are_gone(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $job = $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team), $team);
        $checkpoint = $this->createCheckpoint($event, $job);
        $user = User::factory()->create();

        foreach (['dispatch', 'start', 'complete'] as $action) {
            $this->actingAs($user)->post("/jobs/{$job->id}/{$action}")->assertNotFound();
        }
        $this->actingAs($user)->post("/jobs/{$job->id}/checkpoints/{$checkpoint->id}/complete")->assertNotFound();
        $this->actingAs($user)->post("/jobs/{$job->id}/checkpoints/{$checkpoint->id}/skip", ['reason' => 'x'])->assertNotFound();

        $this->assertSame('pending', $job->refresh()->status);
        $this->assertSame('pending', $checkpoint->refresh()->state);
    }

    public function test_the_legacy_token_api_enforces_job_ownership(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $plan = $this->createPlan($event);
        $me = $this->fieldSupervisor($event);
        $colleague = $this->fieldSupervisor($event);

        $mine = $this->createJob($event, $this->createMovement($event, $plan, $team), $team, ['supervisor_id' => $me->id]);
        $theirs = $this->createJob($event, $this->createMovement($event, $plan, $team), $team, ['supervisor_id' => $colleague->id]);
        $theirCheckpoint = $this->createCheckpoint($event, $theirs);

        Sanctum::actingAs($me);

        $this->getJson("/api/jobs/{$mine->id}/progress?event_id={$event->id}")->assertOk();
        $this->getJson("/api/jobs/{$theirs->id}/progress?event_id={$event->id}")->assertForbidden();
        $this->postJson("/api/checkpoints/{$theirCheckpoint->id}/quick-complete?event_id={$event->id}")->assertForbidden();

        $this->assertSame('pending', $theirCheckpoint->refresh()->state);
    }

    public function test_a_user_without_job_access_is_refused_on_the_legacy_token_api(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $job = $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team), $team);
        $outsider = User::factory()->create();
        $outsider->events()->attach($event->id);

        Sanctum::actingAs($outsider);

        $this->getJson("/api/jobs/{$job->id}/progress?event_id={$event->id}")->assertForbidden();
        $this->getJson("/api/my-jobs?event_id={$event->id}")->assertForbidden();
    }
}
