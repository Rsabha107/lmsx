<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\FleetProvider;
use App\Models\JobCheckpoint;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class MobileViewerTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->event = $this->createEvent();
    }

    private function viewer(?FleetProvider $provider = null): User
    {
        $user = User::factory()->create(['fleet_provider_id' => $provider?->id]);
        $user->assignRole('mobile_viewer');
        $user->events()->attach($this->event);

        return $user;
    }

    /** A job supervised by someone else, with one pending checkpoint. */
    private function jobWithCheckpoint(array $movement = []): array
    {
        $team = $this->createTeam($this->event);
        $supervisor = $this->createProviderUser('ground_control');
        $supervisor->events()->attach($this->event);
        $mv = $this->createMovement($this->event, $this->createPlan($this->event), $team, $movement + ['field_supervisor_id' => $supervisor->id]);
        $job = $this->createJob($this->event, $mv, $team, ['supervisor_id' => $supervisor->id]);

        return [$job, $this->createCheckpoint($this->event, $job)];
    }

    public function test_a_viewer_sees_every_job_of_its_event_without_supervising_it(): void
    {
        [$a] = $this->jobWithCheckpoint();
        [$b] = $this->jobWithCheckpoint();

        Sanctum::actingAs($this->viewer());

        $ids = collect($this->getJson("/api/mobile/jobs?event_id={$this->event->id}")->assertOk()->json('data'))->pluck('id');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $ids->all());

        $this->getJson("/api/mobile/jobs/{$a->id}?event_id={$this->event->id}")
            ->assertOk()
            ->assertJsonPath('data.checkpoints.0.state', 'pending');
        $this->getJson('/api/mobile/me')->assertJsonPath('user.read_only', true);
        $this->getJson("/api/mobile/profile?event_id={$this->event->id}")->assertJsonPath('user.read_only', true);
    }

    public function test_a_viewer_linked_to_a_provider_sees_only_that_providers_jobs(): void
    {
        $other = $this->createProvider('Other Co');
        [$ours] = $this->jobWithCheckpoint();
        $this->jobWithCheckpoint(['fleet_provider_id' => $other->id]);

        Sanctum::actingAs($this->viewer($this->fixtureProvider()));

        $ids = collect($this->getJson("/api/mobile/jobs?event_id={$this->event->id}")->assertOk()->json('data'))->pluck('id');
        $this->assertEquals([$ours->id], $ids->all());
    }

    public function test_a_viewer_cannot_complete_checkpoints_or_report_issues(): void
    {
        [$job, $checkpoint] = $this->jobWithCheckpoint();

        Sanctum::actingAs($this->viewer());

        $this->postJson("/api/mobile/jobs/{$job->id}/checkpoints/{$checkpoint->id}/complete?event_id={$this->event->id}", ['notes' => 'x'])
            ->assertForbidden()
            ->assertJson(['message' => 'Your account can view jobs but not act on them.']);
        $this->postJson("/api/mobile/jobs/{$job->id}/issues?event_id={$this->event->id}", ['type' => 'traffic_delay'])
            ->assertForbidden();
        $this->postJson("/api/checkpoints/{$checkpoint->id}/quick-complete?event_id={$this->event->id}")
            ->assertForbidden();

        $this->assertSame('pending', $checkpoint->refresh()->state);
        $this->assertDatabaseCount('job_issues', 0);
    }

    public function test_a_viewer_signed_in_on_the_web_cannot_act_on_a_job_either(): void
    {
        [$job, $checkpoint] = $this->jobWithCheckpoint();

        $this->actingAs($this->viewer())->withSession(['active_event_id' => $this->event->id])
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/complete")
            ->assertForbidden();
        $this->postJson("/jobs/{$job->job_id}/status", ['status' => 'dispatched'])->assertForbidden();

        $this->assertSame('pending', $checkpoint->refresh()->state);
        $this->assertSame('pending', $job->refresh()->status);
    }

    public function test_field_supervisors_still_act_and_viewers_are_never_offered_as_crew(): void
    {
        [$job, $checkpoint] = $this->jobWithCheckpoint();
        $viewer = $this->viewer();

        Sanctum::actingAs(User::find($job->supervisor_id));
        $this->getJson('/api/mobile/me')->assertJsonPath('user.read_only', false);
        $this->postJson("/api/mobile/jobs/{$job->id}/checkpoints/{$checkpoint->id}/complete?event_id={$this->event->id}")
            ->assertOk();
        $this->assertSame('done', JobCheckpoint::find($checkpoint->id)->state);

        $this->assertFalse(User::fieldSupervisors()->whereKey($viewer->id)->exists());
    }

    public function test_a_viewer_can_download_the_app(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(config('app.mobile_apk'), 'apk');

        $this->actingAs($this->viewer())->get('/downloads/mobile-app')->assertOk();
        $this->get('/get-app')->assertOk();
    }
}
