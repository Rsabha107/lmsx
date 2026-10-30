<?php

namespace Tests\Feature;

use App\Console\Commands\SyncFlightData;
use App\Models\Event;
use App\Models\TeamFlight;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** The header's flight chip (shared flightStatus) and the flight shown on Day Board cards. */
class FlightStatusTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow('2026-11-16 10:00:00');
        Cache::forget(SyncFlightData::LAST_RUN_KEY);
        $this->event = $this->createEvent();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function flight(string $number, string $at, ?string $syncedAt = null, array $extra = []): TeamFlight
    {
        return TeamFlight::create($extra + [
            'event_id' => $this->event->id, 'team_id' => $this->createTeam($this->event)->id, 'direction' => 'arrival',
            'flight_number' => $number, 'scheduled_at' => $at, 'flight_synced_at' => $syncedAt,
        ]);
    }

    public function test_the_header_status_counts_flights_near_now_and_which_are_stale(): void
    {
        $this->flight('QR1', '2026-11-16 11:00:00', '2026-11-16 09:50:00');   // current
        $this->flight('QR2', '2026-11-16 12:00:00', '2026-11-16 07:00:00');   // stale (3 h old, within 3 h)
        $this->flight('QR3', '2026-11-16 09:00:00');                          // never refreshed
        $this->flight('QR4', '2026-11-16 09:30:00', null, ['actual_at' => '2026-11-16 09:25:00']); // landed: not counted
        $this->flight('QR5', '2026-11-16 20:00:00');                          // more than 3 h away
        $this->flight('BUS', '2026-11-16 10:30:00');                          // road leg
        Cache::forever(SyncFlightData::LAST_RUN_KEY, '2026-11-16 09:45:00');

        $this->actingAs($this->admin())->withSession(['active_event_id' => $this->event->id])
            ->get('/day-board')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('flightStatus.show', true)
                ->where('flightStatus.last_run', '2026-11-16 09:45:00')
                ->where('flightStatus.near', 3)
                ->where('flightStatus.stale', 2)
                ->where('flightStatus.now', '2026-11-16 10:00')
                ->where('flightStatus.limits.near_minutes', 60));
    }

    public function test_the_scheduled_sync_records_when_it_ran(): void
    {
        config(['services.aviationstack.key' => 'test', 'services.oag.key' => 'test']);
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->artisan('flights:sync')->assertSuccessful();

        $this->assertSame('2026-11-16 10:00:00', Cache::get(SyncFlightData::LAST_RUN_KEY));
    }

    public function test_the_chip_is_hidden_without_access_to_event_teams_but_the_limits_are_shared(): void
    {
        $supervisor = $this->createProviderUser('ground_control');
        $supervisor->events()->syncWithoutDetaching([$this->event->id]);

        $this->actingAs($supervisor)->withSession(['active_event_id' => $this->event->id])
            ->get('/day-board')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('flightStatus.show', false)
                ->missing('flightStatus.stale')
                ->has('flightStatus.limits'));
    }

    public function test_a_day_board_card_carries_its_flight_and_refresh_state(): void
    {
        $team = $this->createTeam($this->event);
        $flight = TeamFlight::create(['event_id' => $this->event->id, 'team_id' => $team->id, 'direction' => 'arrival',
            'flight_number' => 'QR 194', 'scheduled_at' => '2026-11-16 11:00:00', 'flight_synced_at' => '2026-11-16 09:50:00']);
        $plan = $this->createPlan($this->event);
        $movement = $this->createMovement($this->event, $plan, $team, ['kind' => 'arrival', 'flight_id' => $flight->id,
            'window_start' => '2026-11-16 10:00:00', 'window_end' => '2026-11-16 11:30:00']);
        $this->createJob($this->event, $movement, $team);

        $this->actingAs($this->admin())->withSession(['active_event_id' => $this->event->id])
            ->get('/day-board?date=2026-11-16')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('jobs.0.flight.flight_number', 'QR 194')
                ->where('jobs.0.flight.event_id', $this->event->id)
                ->where('jobs.0.flight.scheduled_wall', '2026-11-16 11:00')
                ->where('jobs.0.flight.synced_at', '2026-11-16 09:50'));
    }
}
