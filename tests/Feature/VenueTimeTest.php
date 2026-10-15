<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\WallClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class VenueTimeTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_the_app_clock_is_qatar_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 07:55:00', 'UTC'));

        $this->assertSame('Asia/Qatar', config('app.timezone'));
        $this->assertSame('2026-10-14 10:55', now()->format('Y-m-d H:i'));
    }

    public function test_stored_times_keep_their_digits_in_the_database_and_in_json(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, ['window_start' => '2026-10-14 09:25:00']);
        $job = $this->createJob($event, $movement, $team);
        $checkpoint = $this->createCheckpoint($event, $job, ['scheduled_at' => '2026-10-14 09:25:00']);

        // TIMESTAMP and DATETIME columns both come back as written.
        $this->assertSame('2026-10-14 09:25:00', DB::table('job_checkpoints')->where('id', $checkpoint->id)->value('scheduled_at'));
        $this->assertSame('2026-10-14 09:25:00', DB::table('movements')->where('id', $movement->id)->value('window_start'));

        $this->assertSame('2026-10-14T09:25:00.000000Z', $checkpoint->fresh()->toArray()['scheduled_at']);
        $this->assertSame('"2026-10-14T09:25:00.000000Z"', json_encode(Carbon::parse('2026-10-14 09:25:00')));
        $this->assertSame('2026-10-14T09:25:00+00:00', WallClock::iso($movement->fresh()->window_start));
    }

    public function test_overdue_is_judged_against_qatar_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 07:00:00', 'UTC')); // 10:00 in Doha

        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $job = $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team), $team);
        $due = $this->createCheckpoint($event, $job, ['scheduled_at' => '2026-10-14 09:25:00']);
        $later = $this->createCheckpoint($event, $job, ['order' => 2, 'scheduled_at' => '2026-10-14 11:00:00']);

        $this->assertTrue($due->isOverdue());
        $this->assertFalse($later->isOverdue());
    }

    public function test_the_shift_command_moves_only_server_clock_values_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-14 12:00:00', 'UTC'));

        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, [
            'window_start' => '2026-10-14 09:25:00', 'actual_departure' => '2026-10-14 06:30:00',
        ]);
        $job = $this->createJob($event, $movement, $team, ['started_at' => '2026-10-14 06:30:00']);
        $device = $this->createCheckpoint($event, $job, [
            'state' => 'done', 'scheduled_at' => '2026-10-14 09:25:00', 'completed_at' => '2026-10-14 09:30:00',
            'received_at' => '2026-10-14 09:31:00', 'event_at' => '2026-10-14 09:30:00', 'time_source' => 'device',
        ]);
        $server = $this->createCheckpoint($event, $job, [
            'order' => 2, 'state' => 'done', 'scheduled_at' => '2026-10-14 10:00:00', 'completed_at' => '2026-10-14 07:10:00',
            'received_at' => '2026-10-14 07:10:00', 'time_source' => 'server',
        ]);
        DB::table('jobs_operations')->where('id', $job->id)->update(['created_at' => '2026-10-14 05:00:00']);

        // Copied from its first checkpoint (venue time already), so it must not move.
        $copiedMovement = $this->createMovement($event, $this->createPlan($event), $team, ['actual_departure' => '2026-10-14 10:12:00']);
        $copied = $this->createJob($event, $copiedMovement, $team, ['started_at' => '2026-10-14 10:12:00']);
        $this->createCheckpoint($event, $copied, ['state' => 'done', 'completed_at' => '2026-10-14 10:12:00', 'event_at' => '2026-10-14 10:12:00', 'time_source' => 'device']);

        $this->artisan('time:shift-to-venue')->assertSuccessful();
        $this->assertSame('2026-10-14 06:30:00', DB::table('jobs_operations')->where('id', $job->id)->value('started_at'));

        $this->artisan('time:shift-to-venue', ['--apply' => true])->assertSuccessful();

        $value = fn ($table, $id, $column) => DB::table($table)->where('id', $id)->value($column);
        $this->assertSame('2026-10-14 09:30:00', $value('jobs_operations', $job->id, 'started_at'));
        $this->assertSame('2026-10-14 08:00:00', $value('jobs_operations', $job->id, 'created_at'));
        $this->assertSame('2026-10-14 09:30:00', $value('movements', $movement->id, 'actual_departure'));
        $this->assertSame('2026-10-14 09:25:00', $value('movements', $movement->id, 'window_start'));
        $this->assertSame('2026-10-14 09:25:00', $value('job_checkpoints', $device->id, 'scheduled_at'));
        $this->assertSame('2026-10-14 09:30:00', $value('job_checkpoints', $device->id, 'completed_at'));
        $this->assertSame('2026-10-14 09:31:00', $value('job_checkpoints', $device->id, 'received_at'));
        $this->assertSame('2026-10-14 10:10:00', $value('job_checkpoints', $server->id, 'completed_at'));
        $this->assertSame('2026-10-14 10:10:00', $value('job_checkpoints', $server->id, 'received_at'));
        $this->assertSame('2026-10-14 10:12:00', $value('jobs_operations', $copied->id, 'started_at'));
        $this->assertSame('2026-10-14 10:12:00', $value('movements', $copiedMovement->id, 'actual_departure'));

        $this->assertTrue(Setting::where('key', 'system.server_times_shifted_to_venue')->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Server timestamps moved to venue time']);

        $this->artisan('time:shift-to-venue', ['--apply' => true])->assertFailed();
        $this->assertSame('2026-10-14 09:30:00', $value('jobs_operations', $job->id, 'started_at'));
    }
}
