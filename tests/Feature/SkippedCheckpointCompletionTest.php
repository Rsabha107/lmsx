<?php

namespace Tests\Feature;

use App\Models\JobOperation;
use App\Models\User;
use App\Services\JobLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class SkippedCheckpointCompletionTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    /** @return array{0: JobOperation, 1: array<int, \App\Models\JobCheckpoint>} */
    private function jobWithSteps(int $count, string $status = 'in-progress'): array
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $job = $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team), $team, [
            'status' => $status, 'started_at' => $status === 'in-progress' ? now() : null,
        ]);

        $steps = [];
        for ($i = 1; $i <= $count; $i++) {
            $steps[] = $this->createCheckpoint($event, $job, [
                'order' => $i, 'name' => "Step $i", 'scheduled_at' => now()->setTime(9 + $i, 0),
            ]);
        }

        return [$job, $steps];
    }

    public function test_skipping_the_last_step_completes_the_job_and_ends_it_at_the_last_done_step(): void
    {
        [$job, [$first, $second, $last]] = $this->jobWithSteps(3);
        $user = User::factory()->create();
        $lifecycle = app(JobLifecycleService::class);

        $lifecycle->completeCheckpoint($first, $user, ['actual_time' => '10:05']);
        $lifecycle->completeCheckpoint($second, $user, ['actual_time' => '11:20']);
        $this->assertSame('in-progress', $job->refresh()->status);

        $lifecycle->skipCheckpoint($last, $user, 'not_needed');

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame('11:20', $job->completed_at->format('H:i'));
        $this->assertSame('10:05', $job->started_at->format('H:i'));
    }

    public function test_a_skipped_middle_step_does_not_stop_the_job_completing(): void
    {
        [$job, [$first, $middle, $last]] = $this->jobWithSteps(3);
        $user = User::factory()->create();
        $lifecycle = app(JobLifecycleService::class);

        $lifecycle->completeCheckpoint($first, $user, ['actual_time' => '10:00']);
        $lifecycle->skipCheckpoint($middle, $user, 'not_needed');
        $lifecycle->completeCheckpoint($last, $user, ['actual_time' => '12:10']);

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame('12:10', $job->completed_at->format('H:i'));
    }

    public function test_a_forced_skip_of_the_last_step_completes_the_job(): void
    {
        [$job, [$first, $last]] = $this->jobWithSteps(2);
        $user = User::factory()->create();
        $lifecycle = app(JobLifecycleService::class);

        $lifecycle->completeCheckpoint($first, $user, ['actual_time' => '10:15']);
        $lifecycle->overrideCheckpoint($last, $user, ['state' => 'missed', 'reason' => 'other']);

        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame('10:15', $job->completed_at->format('H:i'));
    }

    public function test_the_job_stays_open_while_a_step_is_outstanding_or_nothing_was_done(): void
    {
        [$job, [$first, $last]] = $this->jobWithSteps(2, 'pending');
        $user = User::factory()->create();
        $lifecycle = app(JobLifecycleService::class);

        $lifecycle->skipCheckpoint($last, $user, 'not_needed');
        $this->assertSame('in-progress', $job->refresh()->status);

        $lifecycle->skipCheckpoint($first, $user, 'not_needed');
        $this->assertSame('in-progress', $job->refresh()->status);
    }
}
