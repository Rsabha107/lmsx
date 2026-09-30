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
}
