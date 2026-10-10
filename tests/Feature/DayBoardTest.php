<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class DayBoardTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_each_job_shows_its_current_and_previous_checkpoint(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $plan, $team, ['window_start' => '2026-11-24 08:00']);
        $job = $this->createJob($event, $movement, $team);
        $this->createCheckpoint($event, $job, ['order' => 1, 'name' => 'Hotel pickup', 'state' => 'done',
            'scheduled_at' => '2026-11-24 08:05', 'completed_at' => '2026-11-24 08:12', 'is_on_time' => false]);
        $this->createCheckpoint($event, $job, ['order' => 2, 'name' => 'Airport drop', 'scheduled_at' => '2026-11-24 08:30']);
        $this->createCheckpoint($event, $job, ['order' => 3, 'name' => 'Return', 'scheduled_at' => '2026-11-24 09:30']);
        // Another day: counted on the strip, not listed.
        $this->createJob($event, $this->createMovement($event, $plan, $team, ['window_start' => '2026-11-25 10:00']), $team);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->withSession(['active_event_id' => $event->id])
            ->get('/day-board?date=2026-11-24')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('DayBoard')
                ->where('date', '2026-11-24')
                ->has('jobs', 1)
                ->where('jobs.0.start', '2026-11-24 08:05')
                ->where('jobs.0.current_index', 1)
                ->where('jobs.0.current.name', 'Airport drop')
                ->where('jobs.0.previous.name', 'Hotel pickup')
                ->where('jobs.0.previous.delay', 7)
                ->where('jobs.0.previous.on_time', false)
                ->where('jobs.0.next.name', 'Return')
                ->where('dayCounts', ['2026-11-24' => 1, '2026-11-25' => 1]));
    }

    public function test_a_field_supervisor_sees_only_the_jobs_they_supervise(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);

        $supervisor = User::factory()->create();
        $supervisor->assignRole('ground_control');
        $supervisor->functionalAreas()->create(['functional_area' => 'LOG']);

        $mine = $this->createJob($event, $this->createMovement($event, $plan, $team, ['window_start' => '2026-11-24 08:00']), $team, ['supervisor_id' => $supervisor->id]);
        $extra = $this->createMovement($event, $plan, $team, ['window_start' => '2026-11-24 11:00']);
        $extra->extraSupervisors()->attach($supervisor->id);
        $asExtra = $this->createJob($event, $extra, $team);
        $this->createJob($event, $this->createMovement($event, $plan, $team, ['window_start' => '2026-11-24 09:00']), $team);

        $this->actingAs($supervisor)->withSession(['active_event_id' => $event->id])
            ->get('/day-board?date=2026-11-24')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('jobs', 2)
                ->where('jobs.0.id', $mine->id)
                ->where('jobs.1.id', $asExtra->id)
                ->where('dayCounts', ['2026-11-24' => 2]));
    }

    public function test_the_old_schedule_page_redirects_to_the_day_boards_schedule_view(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get('/schedule')->assertRedirect('/day-board?view=schedule');
    }

    public function test_the_schedule_view_lists_that_days_movements_for_what_the_user_may_see(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);

        $supervisor = User::factory()->create();
        $supervisor->assignRole('ground_control');
        $supervisor->functionalAreas()->create(['functional_area' => 'LOG']);

        $this->createJob($event, $this->createMovement($event, $plan, $team, ['window_start' => '2026-11-24 11:00']), $team, ['supervisor_id' => $supervisor->id]);
        $this->createJob($event, $this->createMovement($event, $plan, $team, ['window_start' => '2026-11-24 09:00']), $team, ['supervisor_id' => $supervisor->id]);
        $this->createJob($event, $this->createMovement($event, $plan, $team, ['window_start' => '2026-11-24 10:00']), $team);
        $this->createJob($event, $this->createMovement($event, $plan, $team, ['window_start' => '2026-11-25 10:00']), $team, ['supervisor_id' => $supervisor->id]);

        $as = $this->actingAs($supervisor)->withSession(['active_event_id' => $event->id]);

        // The board view does not send the schedule rows.
        $as->get('/day-board?date=2026-11-24')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('view', 'board')->has('schedule', 0));

        $as->get('/day-board?date=2026-11-24&view=schedule')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('view', 'schedule')
                ->has('schedule', 2)
                ->where('schedule.0.dep', '09:00')
                ->where('schedule.1.dep', '11:00')
                ->has('schedule.0', fn (AssertableInertia $row) => $row
                    ->hasAll(['id', 'code', 'team', 'from', 'to', 'dep', 'arr', 'pax', 'vehicle', 'status', 'delay'])));
    }
}
