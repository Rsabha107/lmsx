<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class JobTimesFromCheckpointsTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private function override(Event $event, int $checkpointId, string $time)
    {
        Permission::firstOrCreate(['name' => 'jobs.override', 'guard_name' => 'web']);
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('jobs.override');

        return $this->actingAs($user)->withSession(['active_event_id' => $event->id])
            ->post("/jobs/checkpoint/{$checkpointId}/override", [
                'state' => 'done', 'reason' => 'app_failure', 'actual_time' => $time, 'exclude_date' => '1',
            ], ['Accept' => 'application/json']);
    }

    public function test_the_job_starts_and_completes_at_its_first_and_last_checkpoint(): void
    {
        $day = now()->subDays(2)->toDateString();
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, [
            'window_start' => "{$day} 15:05", 'window_end' => "{$day} 15:35",
        ]);
        $job = $this->createJob($event, $movement, $team);
        $first = $this->createCheckpoint($event, $job, ['order' => 1, 'scheduled_at' => "{$day} 15:05"]);
        $last = $this->createCheckpoint($event, $job, ['order' => 2, 'scheduled_at' => "{$day} 19:05"]);

        $this->override($event, $first->id, '14:47')->assertOk();
        $this->override($event, $last->id, '18:16')->assertOk();

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame("{$day} 14:47", $job->started_at->format('Y-m-d H:i'));
        $this->assertSame("{$day} 18:16", $job->completed_at->format('Y-m-d H:i'));

        $movement->refresh();
        $this->assertSame("{$day} 14:47", $movement->actual_departure->format('Y-m-d H:i'));
        $this->assertSame("{$day} 18:16", $movement->actual_arrival->format('Y-m-d H:i'));
    }

    public function test_the_reset_command_realigns_existing_jobs_only_with_apply(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, [
            'actual_departure' => '2026-10-13 11:47:19', 'actual_arrival' => '2026-10-13 15:17:02',
        ]);
        $job = $this->createJob($event, $movement, $team, [
            'status' => 'completed', 'started_at' => '2026-10-13 11:47:19', 'completed_at' => '2026-10-13 15:17:02',
        ]);
        $this->createCheckpoint($event, $job, ['order' => 1, 'state' => 'done', 'completed_at' => '2026-10-13 14:47']);
        $this->createCheckpoint($event, $job, ['order' => 2, 'state' => 'done', 'completed_at' => '2026-10-13 18:16']);

        $this->artisan('jobs:recompute-times')->assertSuccessful();
        $this->assertSame('2026-10-13 11:47', $job->refresh()->started_at->format('Y-m-d H:i'));

        $this->artisan('jobs:recompute-times', ['--apply' => true])->assertSuccessful();

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame('2026-10-13 14:47', $job->started_at->format('Y-m-d H:i'));
        $this->assertSame('2026-10-13 18:16', $job->completed_at->format('Y-m-d H:i'));
        $movement->refresh();
        $this->assertSame('2026-10-13 14:47', $movement->actual_departure->format('Y-m-d H:i'));
        $this->assertSame('2026-10-13 18:16', $movement->actual_arrival->format('Y-m-d H:i'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Job times recomputed from checkpoints']);
    }
}
