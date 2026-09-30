<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\Event;
use App\Models\Setting;
use App\Models\TeamFlight;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class OverrideFlightChangeTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private function overrider(Event $event): static
    {
        Permission::firstOrCreate(['name' => 'jobs.override', 'guard_name' => 'web']);
        $user = $this->createUserWithRole('admin');
        $user->givePermissionTo('jobs.override');

        return $this->actingAs($user)->withSession(['active_event_id' => $event->id]);
    }

    private function flightJob(): array
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $flight = TeamFlight::create([
            'event_id' => $event->id,
            'team_id' => $team->id,
            'direction' => 'arrival',
            'flight_number' => 'QR100',
            'scheduled_at' => '2026-11-08 04:00:00',
            'terminal' => 'T1',
        ]);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, [
            'kind' => 'arrival', 'flight_id' => $flight->id, 'flight_number' => 'QR100',
            'window_start' => '2026-11-08 03:00:00', 'window_end' => '2026-11-08 04:30:00',
        ]);
        $job = $this->createJob($event, $movement, $team);

        // "Arrival time at POA staging": 60 min before the flight, as in Settings.
        $template = Checkpoint::create(['code' => 'CKP-POA', 'name' => 'ARRIVAL TIME AT POA STAGING']);
        $settings = app(SettingsService::class);
        $settings->setSetting('movement_offset.arrival', -60, Setting::SCOPE_GLOBAL, null, null, $template->id);
        $settings->setSetting('movement_offset.arrival', -60);

        return [$event, $flight, $movement, $this->createCheckpoint($event, $job, [
            'checkpoint_id' => $template->id, 'name' => $template->name, 'scheduled_at' => '2026-11-08 03:00:00',
        ])];
    }

    public function test_moving_the_flight_re_times_checkpoints_from_the_settings(): void
    {
        [$event, , $movement, $checkpoint] = $this->flightJob();
        // Left an hour out by an earlier save, as on JOB-20260926-0002: the settings put it right again.
        $checkpoint->update(['scheduled_at' => '2026-11-08 02:00:00']);
        $done = $this->createCheckpoint($event, $checkpoint->job, [
            'order' => 0, 'state' => 'done', 'scheduled_at' => '2026-11-08 02:40:00', 'completed_at' => '2026-11-08 02:45:00',
        ]);
        $unconfigured = $this->createCheckpoint($event, $checkpoint->job, ['order' => 2, 'scheduled_at' => '2026-11-08 05:00:00']);

        $this->overrider($event)
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/override", ['flight_scheduled_at' => '2026-11-08T00:00'])
            ->assertOk();

        $this->assertSame('2026-11-07 23:00', $checkpoint->refresh()->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-08 02:40', $done->refresh()->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-08 05:00', $unconfigured->refresh()->scheduled_at->format('Y-m-d H:i'));
        $movement->refresh();
        $this->assertSame('2026-11-07 23:00', $movement->window_start->format('Y-m-d H:i'));
        $this->assertSame('2026-11-08 00:30', $movement->window_end->format('Y-m-d H:i'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'Job flight changed']);
    }

    public function test_a_completion_in_the_same_save_is_judged_against_the_new_time(): void
    {
        [$event, , , $checkpoint] = $this->flightJob();

        $this->overrider($event)
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/override", [
                'state' => 'done',
                'reason' => 'late_arrival',
                'actual_time' => '04:45',
                'exclude_date' => '1',
                'flight_scheduled_at' => '2026-11-08T05:35',
            ])
            ->assertOk();

        $checkpoint->refresh();
        $this->assertSame('2026-11-08 04:35', $checkpoint->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-08 04:45', $checkpoint->completed_at->format('Y-m-d H:i'));
        $this->assertSame(10, $checkpoint->delay_minutes);
    }

    public function test_a_flight_can_be_corrected_without_touching_the_checkpoint(): void
    {
        [$event, $flight, $movement, $checkpoint] = $this->flightJob();

        $this->overrider($event)
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/override", [
                'flight_number' => 'QR102',
                'flight_scheduled_at' => '2026-11-08T05:35',
                'flight_gate' => 'B12',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $flight->refresh();
        $this->assertSame('QR102', $flight->flight_number);
        $this->assertSame('2026-11-08 05:35', $flight->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('B12', $flight->gate);
        $this->assertSame('T1', $flight->terminal);
        $this->assertSame('QR102', $movement->refresh()->flight_number);
        $this->assertSame('pending', $checkpoint->refresh()->state);

        $this->assertDatabaseHas('audit_logs', ['action' => 'Job flight changed']);
    }

    public function test_a_flight_change_is_applied_alongside_a_checkpoint_override(): void
    {
        [$event, $flight, , $checkpoint] = $this->flightJob();

        $this->overrider($event)
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/override", [
                'state' => 'skipped',
                'reason' => 'operational_change',
                'flight_terminal' => 'T2',
            ])
            ->assertOk();

        $this->assertSame('T2', $flight->refresh()->terminal);
        $this->assertSame('skipped', $checkpoint->refresh()->state);
    }

    public function test_sending_the_same_flight_values_is_not_a_change(): void
    {
        [$event, , , $checkpoint] = $this->flightJob();

        $this->overrider($event)
            ->postJson("/jobs/checkpoint/{$checkpoint->id}/override", ['flight_number' => 'QR100'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('audit_logs', ['action' => 'Job flight changed']);
    }
}
