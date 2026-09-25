<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class JobStatsTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_each_job_carries_checkpoint_metrics_for_the_stats_panel(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $plan, $team, ['kind' => 'arrival']);
        $job = $this->createJob($event, $movement, $team);

        $this->createCheckpoint($event, $job, [
            'scheduled_at' => now()->setTime(10, 0),
            'completed_at' => now()->setTime(10, 12),
            'state' => 'done',
            'is_on_time' => false,
            'bags_loaded' => 40, 'planned_bags' => 45,
            'actual_duration_seconds' => 600,
        ]);
        $this->createCheckpoint($event, $job, [
            'scheduled_at' => now()->setTime(11, 0),
            'completed_at' => now()->setTime(11, 0),
            'state' => 'done',
            'is_on_time' => true,
            'bags_loaded' => 20, 'planned_bags' => 20,
            'actual_duration_seconds' => 300,
        ]);

        $user = $this->createUserWithRole('admin');
        $response = $this->actingAs($user)
            ->withSession(['active_event_id' => $event->id])
            ->get('/jobs');

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->where('schedule.0.metrics.checkpointsDone', 2)
            ->where('schedule.0.metrics.onTime', 1)
            ->where('schedule.0.metrics.offSchedule', 1)
            ->where('schedule.0.metrics.varianceCount', 2)
            ->where('schedule.0.metrics.varianceAbsSum', 12)
            ->where('schedule.0.metrics.bags', 60)
            ->where('schedule.0.metrics.checkpointSeconds', 900));
    }

    public function test_an_untouched_job_reports_nothing_rather_than_zero_averages(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $plan, $team, ['kind' => 'departure']);
        $job = $this->createJob($event, $movement, $team);

        $this->createCheckpoint($event, $job, ['scheduled_at' => now(), 'state' => 'pending']);

        $user = $this->createUserWithRole('admin');

        $this->actingAs($user)
            ->withSession(['active_event_id' => $event->id])
            ->get('/jobs')
            ->assertInertia(fn ($page) => $page
                ->where('schedule.0.metrics.checkpointsDone', 0)
                // No completed checkpoints, so there is no span to average.
                ->where('schedule.0.metrics.spanMinutes', null)
                ->where('schedule.0.metrics.varianceCount', 0));
    }
}
