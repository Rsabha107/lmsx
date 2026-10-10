<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class CheckpointTimeEvidenceTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-09 15:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function supervisor(Event $event): User
    {
        foreach (['jobs.view', 'jobs.view-all-functional-areas'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'ground_control', 'guard_name' => 'web'])->syncPermissions(['jobs.view', 'jobs.view-all-functional-areas']);

        $user = User::factory()->create();
        $user->assignRole('ground_control');
        $user->events()->attach($event->id);

        return $user;
    }

    /** @return array{0: User, 1: string, 2: \App\Models\JobCheckpoint} */
    private function setUpCheckpoint(): array
    {
        $event = $this->createEvent();
        $me = $this->supervisor($event);
        $team = $this->createTeam($event);
        $job = $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team), $team, ['supervisor_id' => $me->id]);
        $checkpoint = $this->createCheckpoint($event, $job, ['scheduled_at' => '2026-10-09 14:00:00']);

        Sanctum::actingAs($me);

        return [$me, "/api/mobile/jobs/{$job->id}/checkpoints/{$checkpoint->id}/complete?event_id={$event->id}", $checkpoint];
    }

    public function test_an_offline_completion_keeps_the_time_the_supervisor_acted(): void
    {
        [, $url, $checkpoint] = $this->setUpCheckpoint();

        // Tapped at 14:05, synced at 15:00 with a correct device clock.
        $this->postJson($url, ['event_at' => '2026-10-09T14:05:00+00:00', 'client_sent_at' => '2026-10-09T15:00:00+00:00'])->assertOk();

        $checkpoint->refresh();
        $this->assertSame('device', $checkpoint->time_source);
        $this->assertSame('14:05', $checkpoint->completed_at->format('H:i'));
        $this->assertSame('15:00', $checkpoint->received_at->format('H:i'));
        $this->assertSame(0, $checkpoint->clock_skew_seconds);
    }

    public function test_a_device_clock_that_runs_behind_is_corrected(): void
    {
        [, $url, $checkpoint] = $this->setUpCheckpoint();

        // The phone thinks it is 14:50 when the server says 15:00, so it is 10 minutes behind.
        $this->postJson($url, ['event_at' => '2026-10-09T14:40:00+00:00', 'client_sent_at' => '2026-10-09T14:50:00+00:00'])->assertOk();

        $checkpoint->refresh();
        $this->assertSame(600, $checkpoint->clock_skew_seconds);
        $this->assertSame('14:50', $checkpoint->completed_at->format('H:i'));
        $this->assertSame('14:40', $checkpoint->event_at->format('H:i'));
    }

    public function test_an_implausible_device_time_falls_back_to_the_server_clock(): void
    {
        [, $url, $checkpoint] = $this->setUpCheckpoint();

        $this->postJson($url, ['event_at' => '2026-10-09T18:00:00+00:00', 'client_sent_at' => '2026-10-09T15:00:00+00:00'])->assertOk();

        $checkpoint->refresh();
        $this->assertSame('server', $checkpoint->time_source);
        $this->assertSame('15:00', $checkpoint->completed_at->format('H:i'));
        $this->assertSame('18:00', $checkpoint->event_at->format('H:i'));
    }

    public function test_the_old_hh_mm_field_is_recorded_as_a_manual_time(): void
    {
        [, $url, $checkpoint] = $this->setUpCheckpoint();

        $this->postJson($url, ['actual_time' => '14:10'])->assertOk();

        $checkpoint->refresh();
        $this->assertSame('manual', $checkpoint->time_source);
        $this->assertNotNull($checkpoint->received_at);
        $this->assertNull($checkpoint->event_at);
    }

    public function test_no_time_at_all_uses_the_server_receipt_time(): void
    {
        [, $url, $checkpoint] = $this->setUpCheckpoint();

        $this->postJson($url)->assertOk();

        $this->assertSame('server', $checkpoint->refresh()->time_source);
    }

    public function test_local_venue_times_are_stored_as_wall_clock_not_converted_to_utc(): void
    {
        [, $url, $checkpoint] = $this->setUpCheckpoint();

        // 15:00 UTC is 18:00 in Qatar. The supervisor tapped at 17:05 local.
        $this->postJson($url, ['event_at' => '2026-10-09T17:05:00+03:00', 'client_sent_at' => '2026-10-09T18:00:00+03:00'])->assertOk();

        $checkpoint->refresh();
        $this->assertSame('device', $checkpoint->time_source);
        $this->assertSame('17:05', $checkpoint->completed_at->format('H:i'));
        $this->assertSame('18:00', $checkpoint->received_at->format('H:i'));
        $this->assertSame(0, $checkpoint->clock_skew_seconds);
    }

    public function test_a_time_without_an_offset_is_rejected(): void
    {
        [, $url, $checkpoint] = $this->setUpCheckpoint();

        $this->postJson($url, ['event_at' => '2026-10-09 17:05:00', 'client_sent_at' => '2026-10-09 18:00:00'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['event_at', 'client_sent_at']);

        $this->assertSame('pending', $checkpoint->refresh()->state);
    }

    public function test_a_device_time_needs_the_device_clock_to_go_with_it(): void
    {
        [, $url] = $this->setUpCheckpoint();

        $this->postJson($url, ['event_at' => '2026-10-09T14:05:00+00:00'])->assertStatus(422)->assertJsonValidationErrors('client_sent_at');
    }

    public function test_the_api_reports_the_time_evidence(): void
    {
        [, $url] = $this->setUpCheckpoint();

        $this->postJson($url, ['event_at' => '2026-10-09T14:05:00+00:00', 'client_sent_at' => '2026-10-09T15:00:00+00:00'])
            ->assertOk()
            ->assertJsonPath('data.checkpoints.0.time_source', 'device')
            ->assertJsonPath('data.checkpoints.0.clock_skew_seconds', 0);
    }
}
