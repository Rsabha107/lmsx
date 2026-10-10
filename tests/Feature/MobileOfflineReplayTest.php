<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\JobIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class MobileOfflineReplayTest extends TestCase
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

    private function jobFor(User $user, Event $event, array $overrides = [])
    {
        $team = $this->createTeam($event);

        return $this->createJob(
            $event,
            $this->createMovement($event, $this->createPlan($event), $team),
            $team,
            ['supervisor_id' => $user->id] + $overrides,
        );
    }

    public function test_a_retried_checkpoint_completion_with_the_same_key_succeeds_once(): void
    {
        $event = $this->createEvent();
        $me = $this->fieldSupervisor($event);
        $job = $this->jobFor($me, $event);
        $checkpoint = $this->createCheckpoint($event, $job);
        $url = "/api/mobile/jobs/{$job->id}/checkpoints/{$checkpoint->id}/complete?event_id={$event->id}";

        Sanctum::actingAs($me);

        $this->postJson($url, [], ['Idempotency-Key' => 'op-0001-abcdef'])->assertOk()->assertHeaderMissing('Idempotent-Replayed');
        $completedAt = $checkpoint->refresh()->completed_at;

        $this->postJson($url, [], ['Idempotency-Key' => 'op-0001-abcdef'])
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertEquals($completedAt, $checkpoint->refresh()->completed_at);
    }

    public function test_a_retry_without_a_key_or_with_another_key_still_conflicts(): void
    {
        $event = $this->createEvent();
        $me = $this->fieldSupervisor($event);
        $job = $this->jobFor($me, $event);
        $checkpoint = $this->createCheckpoint($event, $job);
        $url = "/api/mobile/jobs/{$job->id}/checkpoints/{$checkpoint->id}/complete?event_id={$event->id}";

        Sanctum::actingAs($me);

        $this->postJson($url, [], ['Idempotency-Key' => 'op-0001-abcdef'])->assertOk();

        $this->postJson($url)->assertStatus(409);
        $this->postJson($url, [], ['Idempotency-Key' => 'op-0002-abcdef'])->assertStatus(409);
    }

    public function test_the_key_only_replays_for_the_user_who_completed_it(): void
    {
        $event = $this->createEvent();
        $me = $this->fieldSupervisor($event);
        $colleague = $this->fieldSupervisor($event);
        $job = $this->jobFor($me, $event);
        $job->movement->extraSupervisors()->attach($colleague->id);
        $checkpoint = $this->createCheckpoint($event, $job);
        $url = "/api/mobile/jobs/{$job->id}/checkpoints/{$checkpoint->id}/complete?event_id={$event->id}";

        Sanctum::actingAs($me);
        $this->postJson($url, [], ['Idempotency-Key' => 'op-0001-abcdef'])->assertOk();

        Sanctum::actingAs($colleague);
        $this->postJson($url, [], ['Idempotency-Key' => 'op-0001-abcdef'])->assertStatus(409);
    }

    public function test_a_malformed_key_is_rejected(): void
    {
        $event = $this->createEvent();
        $me = $this->fieldSupervisor($event);
        $job = $this->jobFor($me, $event);
        $checkpoint = $this->createCheckpoint($event, $job);

        Sanctum::actingAs($me);

        $this->postJson("/api/mobile/jobs/{$job->id}/checkpoints/{$checkpoint->id}/complete?event_id={$event->id}", [], ['Idempotency-Key' => 'bad key!'])
            ->assertStatus(422);
        $this->assertSame('pending', $checkpoint->refresh()->state);
    }

    public function test_a_retried_issue_report_is_recorded_once(): void
    {
        $event = $this->createEvent();
        $me = $this->fieldSupervisor($event);
        $job = $this->jobFor($me, $event);
        $url = "/api/mobile/jobs/{$job->id}/issues?event_id={$event->id}";
        $type = array_key_first(JobIssue::TYPES);

        Sanctum::actingAs($me);

        $first = $this->postJson($url, ['type' => $type], ['Idempotency-Key' => 'issue-0001-abc'])->assertCreated();
        $second = $this->postJson($url, ['type' => $type], ['Idempotency-Key' => 'issue-0001-abc'])
            ->assertOk()
            ->assertHeader('Idempotent-Replayed', 'true');

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, JobIssue::where('job_id', $job->id)->count());

        $this->postJson($url, ['type' => $type])->assertCreated();
        $this->postJson($url, ['type' => $type])->assertCreated();
    }

    public function test_the_jobs_list_can_be_fetched_as_a_delta(): void
    {
        $event = $this->createEvent();
        $me = $this->fieldSupervisor($event);
        $untouched = $this->jobFor($me, $event);
        $changed = $this->jobFor($me, $event);
        $checkpoint = $this->createCheckpoint($event, $changed);

        // Timestamps are whole seconds, so keep the sync point clear of the fixtures' creation second.
        $this->travel(5)->seconds();

        Sanctum::actingAs($me);

        $full = $this->getJson("/api/mobile/jobs?event_id={$event->id}")->assertOk();
        $this->assertFalse($full->json('delta'));
        $this->assertCount(2, $full->json('data'));

        $since = $full->json('synced_at');

        $this->travel(5)->seconds();
        $this->postJson("/api/mobile/jobs/{$changed->id}/checkpoints/{$checkpoint->id}/complete?event_id={$event->id}")->assertOk();

        $delta = $this->getJson("/api/mobile/jobs?event_id={$event->id}&updated_since=".urlencode($since))->assertOk();

        $this->assertTrue($delta->json('delta'));
        $this->assertSame([$changed->id], collect($delta->json('data'))->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$untouched->id, $changed->id], $delta->json('visible_ids'));
    }
}
