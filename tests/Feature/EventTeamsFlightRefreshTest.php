<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TeamFlight;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Event Teams: bulk flight refresh and the recorded refresh attempts behind each flight's status line. */
class EventTeamsFlightRefreshTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config(['services.aviationstack.key' => 'test', 'services.oag.key' => 'test']);
        Carbon::setTestNow('2026-11-16 04:00:00');
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

    private function flight(string $number, string $scheduledAt, ?Event $event = null): TeamFlight
    {
        $event ??= $this->event;

        return TeamFlight::create([
            'event_id' => $event->id,
            'team_id' => $this->createTeam($event)->id,
            'direction' => 'arrival',
            'flight_number' => $number,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function test_bulk_refresh_updates_found_flights_and_records_the_ones_not_found(): void
    {
        Http::fake([
            'api.oag.com/*' => function ($request) {
                return $request['FlightNumber'] === '4'
                    ? Http::response(['data' => [[
                        'departure' => ['airport' => ['iata' => 'LHR'], 'terminal' => '', 'date' => ['local' => '2026-11-15'], 'time' => ['local' => '21:00']],
                        'arrival' => ['airport' => ['iata' => 'DOH'], 'terminal' => '', 'date' => ['local' => '2026-11-16'], 'time' => ['local' => '06:10']],
                        'statusDetails' => [['state' => 'InFlight']],
                    ]]])
                    : Http::response(['data' => []]);
            },
            'api.aviationstack.com/*' => Http::response(['data' => []]),
        ]);
        $found = $this->flight('QR4', '2026-11-16 06:00:00');
        $missing = $this->flight('XX999', '2026-11-16 07:00:00');
        $other = $this->flight('QR8', '2026-11-16 08:00:00', $this->createEvent());

        $this->actingAs($this->admin())
            ->postJson("/events/{$this->event->id}/flights/sync-bulk", ['flight_ids' => [$found->id, $missing->id, $other->id]])
            ->assertOk()
            ->assertJsonPath('refreshed', 1)
            ->assertJsonPath('failed', 1)
            ->assertJsonCount(2, 'results')
            // The flight's new time is reported with what it did to plans and jobs (none planned here).
            ->assertJsonPath('summary.flights_changed', 1)
            ->assertJsonPath('summary.jobs_retimed', 0)
            ->assertJsonPath('results.0.change.movements', []);

        // The audit entry says only what happened: no empty "failed", no zero counts.
        $meta = json_decode(\App\Models\AuditLog::where('action', 'Flights refreshed')->latest('id')->value('meta'), true);
        $this->assertSame(['QR4'], $meta['new_times']);
        $this->assertSame(['XX999'], $meta['failed']);
        $this->assertArrayNotHasKey('jobs_retimed', $meta);

        $found->refresh();
        $this->assertSame('2026-11-16 06:10', $found->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-16 04:00', $found->flight_synced_at->format('Y-m-d H:i'));
        $this->assertNull($found->flight_sync_error);

        $missing->refresh();
        $this->assertNull($missing->flight_synced_at);
        $this->assertSame('2026-11-16 04:00', $missing->flight_sync_attempted_at->format('Y-m-d H:i'));
        $this->assertStringContainsString('Not found by OAG', $missing->flight_sync_error);

        $this->assertNull($other->refresh()->flight_sync_attempted_at, 'another event\'s flight is left alone');
        $this->assertDatabaseHas('audit_logs', ['action' => 'Flights refreshed']);
    }

    public function test_the_scheduled_sync_records_a_failed_attempt(): void
    {
        Http::fake(['api.oag.com/*' => Http::response(['data' => []]), 'api.aviationstack.com/*' => Http::response(['data' => []])]);
        $flight = $this->flight('XX1', '2026-11-16 06:00:00');

        $this->artisan('flights:sync')->expectsOutputToContain('Failed:  1')->assertSuccessful();

        $this->assertNotNull($flight->refresh()->flight_sync_attempted_at);
        $this->assertNotNull($flight->flight_sync_error);
        $this->assertNull($flight->flight_synced_at);
    }

    public function test_only_event_managers_can_bulk_refresh_and_the_list_is_capped(): void
    {
        $flight = $this->flight('QR4', '2026-11-16 06:00:00');

        $this->actingAs($this->createUserWithRole('transport'))
            ->postJson("/events/{$this->event->id}/flights/sync-bulk", ['flight_ids' => [$flight->id]])
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->postJson("/events/{$this->event->id}/flights/sync-bulk", ['flight_ids' => range(1, 61)])
            ->assertUnprocessable();
    }

    public function test_the_page_gets_the_refresh_limits_and_the_venue_clock(): void
    {
        $admin = $this->admin();
        $admin->events()->attach($this->event);

        $this->actingAs($admin)->withSession(['active_event_id' => $this->event->id])
            ->get('/event-teams')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('EventTeams')
                ->where('flightLimits', ['near_minutes' => 60, 'far_hours' => 6, 'unconfirmed_minutes' => 120])
                ->where('serverNow', '2026-11-16 04:00:00'));
    }
}
