<?php

namespace Tests\Feature;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class OverrideExcludeDateTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private function overrider(Event $event): static
    {
        Permission::firstOrCreate(['name' => 'jobs.override', 'guard_name' => 'web']);
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('jobs.override');

        return $this->actingAs($user)->withSession(['active_event_id' => $event->id]);
    }

    private function checkpointAt(Carbon $scheduled, ?Carbon $windowEnd = null)
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, [
            'window_start' => $scheduled->copy()->subHour(),
            'window_end' => $windowEnd ?? $scheduled->copy()->addHour(),
        ]);
        $job = $this->createJob($event, $movement, $team);

        return [$event, $this->createCheckpoint($event, $job, ['scheduled_at' => $scheduled])];
    }

    /** Sent the way the Jobs page's modal sends it: multipart, booleans as "1"/"0". */
    private function override(Event $event, int $checkpointId, string $time, string $excludeDate)
    {
        return $this->overrider($event)->post("/jobs/checkpoint/{$checkpointId}/override", [
            'state' => 'done',
            'reason' => 'app_failure',
            'actual_time' => $time,
            'exclude_date' => $excludeDate,
        ], ['Accept' => 'application/json']);
    }

    public function test_excluding_the_date_stores_the_time_on_the_scheduled_day(): void
    {
        $scheduled = now()->subDays(3)->setTime(10, 0);
        [$event, $checkpoint] = $this->checkpointAt($scheduled, $scheduled->copy()->addMinutes(15));

        $this->override($event, $checkpoint->id, '10:20', '1')->assertOk();

        $checkpoint->refresh();
        $this->assertSame($scheduled->copy()->setTime(10, 20)->format('Y-m-d H:i'), $checkpoint->completed_at->format('Y-m-d H:i'));
        $this->assertSame(20 * 60, $checkpoint->actual_duration_seconds);
        $this->assertFalse((bool) $checkpoint->is_on_time);
        $this->assertSame(20, $checkpoint->delay_minutes);
    }

    public function test_excluding_the_date_takes_the_nearest_day_across_midnight(): void
    {
        // Planned 00:10, done 23:55 the evening before: 15 minutes early, as the modal shows.
        $scheduled = now()->subDays(3)->setTime(0, 10);
        [$event, $checkpoint] = $this->checkpointAt($scheduled);

        $this->override($event, $checkpoint->id, '23:55', '1')->assertOk();

        $checkpoint->refresh();
        $this->assertSame($scheduled->copy()->subDay()->setTime(23, 55)->format('Y-m-d H:i'), $checkpoint->completed_at->format('Y-m-d H:i'));
        $this->assertSame(15 * 60, $checkpoint->actual_duration_seconds);
        $this->assertTrue((bool) $checkpoint->is_on_time);
    }

    public function test_an_early_morning_plan_done_the_evening_before_is_early_not_a_day_late(): void
    {
        // Planned 04:00, done 22:27: the modal shows 333 min early, not 1107 min late.
        $scheduled = now()->addDays(5)->setTime(4, 0);
        [$event, $checkpoint] = $this->checkpointAt($scheduled);

        $this->override($event, $checkpoint->id, '22:27', '1')->assertOk();

        $this->assertSame(-333, $checkpoint->refresh()->delay_minutes);
    }

    public function test_without_excluding_the_date_the_time_is_today(): void
    {
        $scheduled = now()->subDays(3)->setTime(10, 0);
        [$event, $checkpoint] = $this->checkpointAt($scheduled);

        $this->override($event, $checkpoint->id, '10:20', '0')->assertOk();

        $this->assertSame(now()->setTime(10, 20)->format('Y-m-d H:i'), $checkpoint->refresh()->completed_at->format('Y-m-d H:i'));
    }

    public function test_punctuality_is_judged_against_the_checkpoints_own_time(): void
    {
        // Early for its own 19:05 slot, though after the movement window ended at 18:35.
        $scheduled = now()->subDays(3)->setTime(19, 5);
        [$event, $checkpoint] = $this->checkpointAt($scheduled, $scheduled->copy()->setTime(18, 35));

        $this->override($event, $checkpoint->id, '18:59', '1')->assertOk();

        $checkpoint->refresh();
        $this->assertTrue($checkpoint->is_on_time);
        $this->assertSame(0, $checkpoint->getRawOriginal('delay_minutes'));
    }

    public function test_job_start_and_finish_follow_the_overridden_times(): void
    {
        $scheduled = now()->subDays(3)->setTime(10, 0);
        [$event, $first] = $this->checkpointAt($scheduled);
        $job = $first->job;
        $last = $this->createCheckpoint($event, $job, ['order' => 2, 'scheduled_at' => $scheduled->copy()->addHour()]);
        $at = fn (string $time) => $scheduled->copy()->setTime(...array_map('intval', explode(':', $time)))->format('Y-m-d H:i');

        $this->override($event, $first->id, '10:20', '1')->assertOk();
        $this->assertSame($at('10:20'), $job->refresh()->started_at->format('Y-m-d H:i'));
        $this->assertSame($at('10:20'), $job->movement->actual_departure->format('Y-m-d H:i'));

        // Correcting the time moves the start with it.
        $this->override($event, $first->id, '10:05', '1')->assertOk();
        $this->assertSame($at('10:05'), $job->refresh()->started_at->format('Y-m-d H:i'));

        $this->override($event, $last->id, '11:10', '1')->assertOk();
        $job->refresh();
        $this->assertSame('completed', $job->status);
        $this->assertSame($at('11:10'), $job->completed_at->format('Y-m-d H:i'));
        $this->assertSame($at('11:10'), $job->movement->fresh()->actual_arrival->format('Y-m-d H:i'));
    }

    public function test_a_mistyped_middle_step_does_not_move_the_finish(): void
    {
        $scheduled = now()->subDays(3)->setTime(10, 0);
        [$event, $first] = $this->checkpointAt($scheduled);
        $job = $first->job;
        $middle = $this->createCheckpoint($event, $job, ['order' => 2, 'scheduled_at' => $scheduled->copy()->addMinutes(30)]);
        $last = $this->createCheckpoint($event, $job, ['order' => 3, 'scheduled_at' => $scheduled->copy()->addHour()]);

        $this->override($event, $first->id, '10:00', '1')->assertOk();
        $this->override($event, $middle->id, '21:30', '1')->assertOk();
        $this->override($event, $last->id, '11:00', '1')->assertOk();

        $job->refresh();
        $this->assertSame($scheduled->copy()->setTime(10, 0)->format('Y-m-d H:i'), $job->started_at->format('Y-m-d H:i'));
        $this->assertSame($scheduled->copy()->setTime(11, 0)->format('Y-m-d H:i'), $job->completed_at->format('Y-m-d H:i'));
    }

    public function test_a_checkpoint_without_a_scheduled_time_is_neither_on_time_nor_late(): void
    {
        [$event, $checkpoint] = $this->checkpointAt(now()->subDays(3)->setTime(10, 0));
        $checkpoint->update(['scheduled_at' => null]);

        $this->override($event, $checkpoint->id, '23:00', '0')->assertOk();

        $checkpoint->refresh();
        $this->assertNull($checkpoint->is_on_time);
        $this->assertNull($checkpoint->getRawOriginal('delay_minutes'));
    }
}
