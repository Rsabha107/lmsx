<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TeamFlight;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class OagFlightSyncTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.aviationstack.key' => 'test', 'services.oag.key' => 'test']);
        $this->event = $this->createEvent();
    }

    private function oagLeg(string $depDate, string $depTime, string $arrDate, string $arrTime, string $state = 'Scheduled'): array
    {
        return [
            'departure' => ['airport' => ['iata' => 'LHR'], 'terminal' => '', 'date' => ['local' => $depDate], 'time' => ['local' => $depTime]],
            'arrival' => ['airport' => ['iata' => 'DOH'], 'terminal' => '', 'date' => ['local' => $arrDate], 'time' => ['local' => $arrTime]],
            'statusDetails' => [['state' => $state]],
        ];
    }

    private function flight(string $scheduledAt, array $attributes = []): TeamFlight
    {
        return TeamFlight::create($attributes + [
            'event_id' => $this->event->id,
            'team_id' => $this->createTeam($this->event)->id,
            'direction' => 'arrival',
            'flight_number' => 'QR4',
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function test_an_overnight_arrival_takes_the_flight_that_lands_on_its_day(): void
    {
        Http::fake(['api.oag.com/*' => Http::response(['data' => [
            $this->oagLeg('2026-11-15', '21:00', '2026-11-16', '06:10', 'InGate'),
            $this->oagLeg('2026-11-16', '21:00', '2026-11-17', '06:10'),
        ]])]);
        $flight = $this->flight('2026-11-16 06:00:00');
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->postJson("/events/{$this->event->id}/flights/{$flight->id}/sync")
            ->assertOk()->assertJsonPath('source', 'oag');

        $this->assertSame('2026-11-16 06:10', $flight->refresh()->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('landed', $flight->flight_status);
        Http::assertSent(fn (Request $request) => $request['DepartureDateTime'] === '2026-11-15/2026-11-16');
    }

    public function test_airline_codes_with_a_digit_are_looked_up_on_oag(): void
    {
        Http::fake(['api.oag.com/*' => Http::response(['data' => [$this->oagLeg('2026-10-14', '13:10', '2026-10-14', '14:35', 'InGate')]])]);
        $flight = $this->flight('2026-10-14 14:30:00', ['flight_number' => 'J9115']);

        $this->assertNotNull(app(\App\Services\OagScheduleService::class)->lookupSchedule($flight));
        Http::assertSent(fn (Request $request) => $request['CarrierCode'] === 'J9' && $request['FlightNumber'] === '115');
    }

    public function test_no_oag_flight_on_that_day_falls_back_to_aviationstack(): void
    {
        Http::fake([
            'api.oag.com/*' => Http::response(['data' => [$this->oagLeg('2026-11-17', '21:00', '2026-11-18', '06:10')]]),
            'api.aviationstack.com/*' => Http::response(['data' => [[
                'flight_date' => '2026-11-16', 'flight_status' => 'active',
                'departure' => [], 'arrival' => ['estimated' => '2026-11-16T06:25:00+00:00'],
            ]]]),
        ]);
        $flight = $this->flight('2026-11-16 06:00:00');
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->postJson("/events/{$this->event->id}/flights/{$flight->id}/sync")
            ->assertOk()->assertJsonPath('source', 'aviationstack');

        $this->assertSame('2026-11-16 06:00', $flight->refresh()->scheduled_at->format('Y-m-d H:i'), 'a different day never moves the flight');
        $this->assertSame('active', $flight->flight_status);
    }

    public function test_the_scheduled_sync_refreshes_only_flights_due_around_now(): void
    {
        Carbon::setTestNow('2026-11-16 04:00:00');
        Http::fake(['api.oag.com/*' => Http::response(['data' => [$this->oagLeg('2026-11-15', '21:00', '2026-11-16', '06:10', 'InFlight')]])]);

        $soon = $this->flight('2026-11-16 06:00:00');
        $later = $this->flight('2026-11-16 12:00:00', ['flight_synced_at' => '2026-11-16 03:00:00']);
        $tomorrow = $this->flight('2026-11-17 06:00:00');
        $landed = $this->flight('2026-11-16 03:00:00', ['flight_status' => 'landed']);
        $bus = $this->flight('2026-11-16 05:00:00', ['flight_number' => 'BUS']);

        $this->artisan('flights:sync')->expectsOutputToContain('Synced:  1')->assertSuccessful();

        $this->assertSame('active', $soon->refresh()->flight_status);
        $this->assertSame('2026-11-16 06:10', $soon->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-16 03:00', $later->refresh()->flight_synced_at->format('Y-m-d H:i'), 'synced an hour ago, 8 h out');
        $this->assertNull($tomorrow->refresh()->flight_synced_at);
        $this->assertNull($landed->refresh()->flight_synced_at);
        $this->assertNull($bus->refresh()->flight_synced_at);
        Http::assertSentCount(1);
    }
}
