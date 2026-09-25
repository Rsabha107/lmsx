<?php

namespace Tests\Feature;

use App\Models\TeamFlight;
use App\Services\SettingsService;
use App\Services\TeamImportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class TeamFlightReimportTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_a_retimed_flight_with_the_same_number_updates_the_flight_and_its_planned_movements(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $plan = $this->createPlan($event);

        $flight = TeamFlight::create([
            'event_id' => $event->id,
            'team_id' => $team->id,
            'direction' => 'arrival',
            'flight_number' => 'QR 194',
            'scheduled_at' => '2026-11-16 23:05:00',
            'estimated_at' => '2026-11-16 23:20:00',
            'party_size_total' => 36,
        ]);

        $planned = $this->createMovement($event, $plan, $team, [
            'kind' => 'arrival',
            'flight_id' => $flight->id,
            'window_start' => '2026-11-16 23:05:00',
            'window_end' => '2026-11-16 23:45:00',
            'passengers' => 36,
        ]);

        $dispatched = $this->createMovement($event, $plan, $team, [
            'kind' => 'arrival',
            'flight_id' => $flight->id,
            'window_start' => '2026-11-16 23:05:00',
            'window_end' => '2026-11-16 23:45:00',
            'passengers' => 36,
            'job_id' => 'JOB-TEST-0001',
        ]);

        $other = $this->createTeam($event);

        $result = app(TeamImportService::class)->import([[
            'trigram' => $team->code,
            'arrival_flight_number' => 'QR 194',
            'arrival_date' => '2026-11-16',
            'arrival_time' => '22:30',
            'arrival_passengers' => '34',
        ]], $event->id);

        $this->assertSame(1, $result['updated']);

        $flight->refresh();
        $this->assertSame('2026-11-16 22:30', $flight->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame(34, $flight->party_size_total);
        $this->assertNotNull($flight->estimated_at, 'same flight on the same day keeps its live tracking');

        $offset = app(SettingsService::class)->getMovementOffset('arrival', $event->id);
        $planned->refresh();
        $this->assertTrue(
            Carbon::parse('2026-11-16 22:30')->addMinutes($offset)->equalTo($planned->window_start)
        );
        $this->assertEquals(40, $planned->window_start->diffInMinutes($planned->window_end));
        $this->assertSame(34, $planned->passengers);

        $dispatched->refresh();
        $this->assertSame('2026-11-16 23:05', $dispatched->window_start->format('Y-m-d H:i'));
        $this->assertSame(36, $dispatched->passengers);

        $change = $result['flight_changes'][0];
        $statuses = collect($change['movements'])->pluck('status', 'id');
        $this->assertSame('updated', $statuses[$planned->id]);
        $this->assertSame('needs_review', $statuses[$dispatched->id]);

        $this->assertSame([$other->code], $result['not_in_file']);
    }

    public function test_a_date_without_a_time_keeps_the_known_time(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);

        $flight = TeamFlight::create([
            'event_id' => $event->id,
            'team_id' => $team->id,
            'direction' => 'arrival',
            'flight_number' => 'QR 194',
            'scheduled_at' => '2026-11-16 23:05:00',
        ]);

        $result = app(TeamImportService::class)->import([[
            'trigram' => $team->code,
            'arrival_flight_number' => 'QR 194',
            'arrival_date' => '2026-11-16',
        ]], $event->id);

        $this->assertSame(1, $result['unchanged']);
        $this->assertSame('2026-11-16 23:05', $flight->refresh()->scheduled_at->format('Y-m-d H:i'));
    }

    public function test_a_rebooked_flight_drops_the_old_flights_live_tracking(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);

        $flight = TeamFlight::create([
            'event_id' => $event->id,
            'team_id' => $team->id,
            'direction' => 'arrival',
            'flight_number' => 'QR 194',
            'scheduled_at' => '2026-11-16 23:05:00',
            'estimated_at' => '2026-11-16 23:20:00',
            'delay_minutes' => 15,
        ]);

        app(TeamImportService::class)->import([[
            'trigram' => $team->code,
            'arrival_flight_number' => 'QR 196',
            'arrival_date' => '2026-11-17',
            'arrival_time' => '06:10',
        ]], $event->id);

        $flight->refresh();
        $this->assertSame('QR 196', $flight->flight_number);
        $this->assertNull($flight->estimated_at);
        $this->assertNull($flight->delay_minutes);
    }
}
