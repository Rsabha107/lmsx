<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Services\DriverDayStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class DriverDayStatusTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-06-10 12:00:00');
    }

    private function driver(string $status = 'available'): Driver
    {
        return Driver::create(['name' => 'Driver '.uniqid(), 'status' => $status]);
    }

    /** Give the driver a movement running between two times today, with an optional job status. */
    private function assign(Driver $driver, string $from, string $to, ?string $jobStatus = null): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, [
            'driver_id' => $driver->id,
            'window_start' => $this->now->copy()->setTimeFromTimeString($from),
            'window_end' => $this->now->copy()->setTimeFromTimeString($to),
        ]);

        if ($jobStatus) {
            $this->createJob($event, $movement, $team, ['driver_id' => $driver->id, 'status' => $jobStatus]);
        }
    }

    private function stateOf(Driver $driver): array
    {
        return app(DriverDayStatusService::class)->forDrivers(collect([$driver]), $this->now)[$driver->id];
    }

    public function test_a_driver_with_nothing_today_is_idle(): void
    {
        $driver = $this->driver();
        $this->assertSame('idle', $this->stateOf($driver)['state']);
    }

    public function test_a_driver_inside_a_movement_window_is_on_a_job(): void
    {
        $driver = $this->driver();
        $this->assign($driver, '11:30', '12:30');

        $this->assertSame('on_job', $this->stateOf($driver)['state']);
    }

    public function test_a_job_still_in_progress_past_its_window_keeps_the_driver_on_it(): void
    {
        $driver = $this->driver();
        $this->assign($driver, '10:00', '11:00', 'in-progress');

        $this->assertSame('on_job', $this->stateOf($driver)['state']);
    }

    public function test_a_short_gap_between_jobs_is_on_shift(): void
    {
        $driver = $this->driver();
        $this->assign($driver, '10:00', '11:30');
        $this->assign($driver, '12:30', '13:30');

        $this->assertSame('on_shift', $this->stateOf($driver)['state']);
    }

    public function test_a_long_break_between_jobs_is_rest(): void
    {
        $driver = $this->driver();
        $this->assign($driver, '08:00', '09:00');
        $this->assign($driver, '15:00', '16:00');

        $this->assertSame('rest', $this->stateOf($driver)['state']);
    }

    public function test_before_the_first_job_the_driver_is_scheduled(): void
    {
        $driver = $this->driver();
        $this->assign($driver, '15:00', '16:00');

        $this->assertSame('scheduled', $this->stateOf($driver)['state']);
    }

    public function test_after_the_last_job_the_shift_is_done(): void
    {
        $driver = $this->driver();
        $this->assign($driver, '08:00', '09:00');

        $this->assertSame('done', $this->stateOf($driver)['state']);
    }

    public function test_an_early_completion_ends_the_job(): void
    {
        $driver = $this->driver();
        $this->assign($driver, '11:30', '12:30', 'completed');

        $this->assertSame('done', $this->stateOf($driver)['state']);
    }

    public function test_a_cancelled_job_does_not_count(): void
    {
        $driver = $this->driver();
        $this->assign($driver, '11:30', '12:30', 'cancelled');

        $this->assertSame('idle', $this->stateOf($driver)['state']);
    }

    public function test_marked_off_wins_and_flags_any_assignments(): void
    {
        $driver = $this->driver('off');
        $this->assign($driver, '11:30', '12:30');

        $state = $this->stateOf($driver);
        $this->assertSame('off', $state['state']);
        $this->assertSame(1, $state['jobs_today']);
        $this->assertStringContainsString('assigned 1 job', $state['detail']);
    }

    public function test_the_fleet_page_ships_the_state_with_each_driver(): void
    {
        $driver = $this->driver();
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'fleet.view', 'guard_name' => 'web']);

        $this->actingAs($this->createUserWithRole('admin'))
            ->get('/fleet')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('drivers.0.id', $driver->id)->where('drivers.0.today.state', 'idle'));
    }
}
