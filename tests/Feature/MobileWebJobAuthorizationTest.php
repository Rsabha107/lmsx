<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class MobileWebJobAuthorizationTest extends TestCase
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

    private function jobFor(User $user, Event $event)
    {
        $team = $this->createTeam($event);

        return $this->createJob(
            $event,
            $this->createMovement($event, $this->createPlan($event), $team),
            $team,
            ['supervisor_id' => $user->id, 'status' => 'in-progress'],
        );
    }

    private function as(User $user, Event $event): static
    {
        return $this->actingAs($user)->withSession(['active_event_id' => $event->id]);
    }

    public function test_a_supervisor_only_lists_and_opens_their_own_jobs_in_the_browser(): void
    {
        $event = $this->createEvent();
        $me = $this->fieldSupervisor($event);
        $colleague = $this->fieldSupervisor($event);
        $mine = $this->jobFor($me, $event);
        $theirs = $this->jobFor($colleague, $event);

        $this->as($me, $event)->get('/jobs/mobile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('schedule', fn ($rows) => collect($rows)->pluck('jobId')->all() === [$mine->job_id]));

        $this->as($me, $event)->get("/jobs/mobile/{$mine->job_id}")->assertOk();
        $this->as($me, $event)->get("/jobs/mobile/{$theirs->job_id}")->assertForbidden();
        $this->as($me, $event)->get("/jobs/mobile/{$theirs->id}")->assertForbidden();
    }

    public function test_a_supervisor_cannot_complete_or_view_evidence_on_a_colleagues_job(): void
    {
        $event = $this->createEvent();
        $me = $this->fieldSupervisor($event);
        $colleague = $this->fieldSupervisor($event);
        $theirs = $this->jobFor($colleague, $event);
        $checkpoint = $this->createCheckpoint($event, $theirs);

        $this->as($me, $event)->postJson("/jobs/checkpoint/{$checkpoint->id}/complete")->assertForbidden();
        $this->as($me, $event)->post("/jobs/{$theirs->id}/status", ['status' => 'completed'])->assertForbidden();

        $this->assertSame('pending', $checkpoint->refresh()->state);
    }

    public function test_a_job_in_another_event_is_not_reachable_from_the_active_one(): void
    {
        $event = $this->createEvent();
        $other = $this->createEvent();
        $me = $this->fieldSupervisor($event);
        $elsewhere = $this->jobFor($me, $other);

        $this->as($me, $event)->get("/jobs/mobile/{$elsewhere->job_id}")->assertForbidden();
    }
}
