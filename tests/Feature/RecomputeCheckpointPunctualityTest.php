<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class RecomputeCheckpointPunctualityTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_it_rejudges_completed_checkpoints_only_with_apply(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $job = $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team), $team);

        $early = $this->createCheckpoint($event, $job, ['state' => 'done', 'scheduled_at' => '2026-10-13 19:05', 'completed_at' => '2026-10-13 18:59', 'is_on_time' => false, 'delay_minutes' => 24]);
        $unscheduled = $this->createCheckpoint($event, $job, ['order' => 2, 'state' => 'done', 'scheduled_at' => null, 'completed_at' => '2026-10-13 20:19', 'is_on_time' => false, 'delay_minutes' => 104]);
        $late = $this->createCheckpoint($event, $job, ['order' => 3, 'state' => 'done', 'scheduled_at' => '2026-10-13 20:00', 'completed_at' => '2026-10-13 20:30', 'is_on_time' => true, 'delay_minutes' => 0]);

        $this->artisan('checkpoints:recompute-punctuality')->assertSuccessful();
        $this->assertFalse($early->refresh()->is_on_time);

        $this->artisan('checkpoints:recompute-punctuality', ['--apply' => true])->assertSuccessful();

        $early->refresh();
        $this->assertTrue($early->is_on_time);
        $this->assertSame(0, $early->getRawOriginal('delay_minutes'));

        $unscheduled->refresh();
        $this->assertNull($unscheduled->is_on_time);
        $this->assertNull($unscheduled->getRawOriginal('delay_minutes'));

        $late->refresh();
        $this->assertFalse($late->is_on_time);
        $this->assertSame(30, $late->getRawOriginal('delay_minutes'));

        $this->assertDatabaseHas('audit_logs', ['action' => 'Checkpoint punctuality recomputed']);
    }
}
