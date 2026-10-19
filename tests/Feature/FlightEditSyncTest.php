<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Plan;
use App\Models\Team;
use App\Models\TeamFlight;
use App\Models\User;
use App\Services\SettingsService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class FlightEditSyncTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    private Team $team;

    private TeamFlight $flight;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->event = $this->createEvent();
        $this->team = $this->createTeam($this->event);
        $this->flight = TeamFlight::create([
            'event_id' => $this->event->id,
            'team_id' => $this->team->id,
            'direction' => 'arrival',
            'flight_number' => 'QR 194',
            'scheduled_at' => '2026-11-16 23:05:00',
            'estimated_at' => '2026-11-16 23:20:00',
            'party_size_total' => 36,
        ]);
    }

    private function planOn(string $date): Plan
    {
        return tap($this->createPlan($this->event))->update(['date' => $date]);
    }

    private function arrival(Plan $plan, array $attributes = [])
    {
        return $this->createMovement($this->event, $plan, $this->team, $attributes + [
            'kind' => 'arrival',
            'flight_id' => $this->flight->id,
            'flight_number' => 'QR 194',
            'window_start' => '2026-11-16 23:05:00',
            'window_end' => '2026-11-16 23:45:00',
            'passengers' => 36,
        ]);
    }

    private function edit(array $changes)
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $this->actingAs($admin)->put("/events/{$this->event->id}/flights/{$this->flight->id}", $changes + [
            'direction' => 'arrival',
            'flight_number' => $this->flight->flight_number,
            'scheduled_at' => $this->flight->scheduled_at->format('Y-m-d H:i:s'),
            'party_size_total' => $this->flight->party_size_total,
        ]);
    }

    public function test_editing_a_flight_re_times_its_planned_movements_and_leaves_jobs_alone(): void
    {
        $plan = $this->planOn('2026-11-16');
        $planned = $this->arrival($plan);
        $issued = $this->arrival($plan, ['job_id' => 'JOB-TEST-0001']);

        $this->edit(['flight_number' => 'QR 196', 'scheduled_at' => '2026-11-16 21:30:00', 'party_size_total' => 34])
            ->assertRedirect()
            ->assertSessionHas('success', 'Flight updated. 1 planned movement synced. 1 movement already has a job and must be updated by hand.')
            ->assertSessionHas('flight_change', fn ($change) => collect($change['movements'])->pluck('status', 'code')->all() === [
                $planned->code => 'updated',
                $issued->code => 'needs_review',
            ]);

        $offset = app(SettingsService::class)->getMovementOffset('arrival', $this->event->id);
        $planned->refresh();
        $this->assertTrue(Carbon::parse('2026-11-16 21:30')->addMinutes($offset)->equalTo($planned->window_start));
        $this->assertEquals(40, $planned->window_start->diffInMinutes($planned->window_end));
        $this->assertSame(34, $planned->passengers);
        $this->assertSame('QR 196', $planned->flight_number);

        $issued->refresh();
        $this->assertSame('2026-11-16 23:05', $issued->window_start->format('Y-m-d H:i'));
        $this->assertSame('QR 194', $issued->flight_number);

        $this->assertNull($this->flight->refresh()->estimated_at, 'a rebooked flight drops the old live tracking');
        $this->assertDatabaseHas('audit_logs', ['action' => 'Flight updated']);
    }

    public function test_a_teams_own_plan_follows_the_flight_to_another_day_but_a_shared_one_does_not(): void
    {
        $own = $this->planOn('2026-11-16');
        $ownMovement = $this->arrival($own);

        $shared = $this->planOn('2026-11-16');
        $sharedMovement = $this->arrival($shared);
        $otherTeam = $this->createTeam($this->event);
        $this->createMovement($this->event, $shared, $otherTeam, ['kind' => 'arrival']);

        $this->edit(['scheduled_at' => '2026-11-17 09:00:00'])->assertSessionHasNoErrors();

        $this->assertSame('2026-11-17', $own->refresh()->date->toDateString());
        $this->assertSame('2026-11-16', $shared->refresh()->date->toDateString());

        $notes = collect(session('flight_change')['movements'])->pluck('notes', 'code');
        $this->assertContains('Plan date moved Mon 16 Nov → Tue 17 Nov.', $notes[$ownMovement->code]);
        $this->assertStringContainsString('shared with other teams', implode(' ', $notes[$sharedMovement->code]));
    }

    public function test_editing_only_the_gate_touches_no_plan(): void
    {
        $movement = $this->arrival($this->planOn('2026-11-16'));

        $this->edit(['gate' => 'B12'])
            ->assertSessionHas('success', 'Flight updated.')
            ->assertSessionMissing('flight_change');

        $this->assertSame('2026-11-16 23:05', $movement->refresh()->window_start->format('Y-m-d H:i'));
        $this->assertNotNull($this->flight->refresh()->estimated_at);
    }
}
