<?php

namespace Tests\Feature;

use App\Models\TeamFlight;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class OagFlightStatusTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private function oagFlight(array $statusDetails): array
    {
        $leg = fn (string $iata, string $time) => [
            'airport' => ['iata' => $iata], 'terminal' => '',
            'date' => ['local' => '2026-10-28'], 'time' => ['local' => $time],
        ];

        return ['data' => [[
            'departure' => $leg('BAH', '16:20'),
            'arrival' => $leg('DOH', '17:20'),
            'statusDetails' => $statusDetails,
        ]]];
    }

    private function sync(array $oag): array
    {
        $this->seed(RolePermissionSeeder::class);
        config(['services.aviationstack.key' => 'test', 'services.oag.key' => 'test']);
        Http::fake([
            'api.aviationstack.com/*' => Http::response(['data' => [['flight_date' => '2026-10-30']]]),
            'api.oag.com/*' => Http::response($oag),
        ]);

        $event = $this->createEvent();
        $flight = TeamFlight::create([
            'event_id' => $event->id,
            'team_id' => $this->createTeam($event)->id,
            'direction' => 'arrival',
            'flight_number' => 'GF526',
            'scheduled_at' => '2026-10-28 17:20:00',
        ]);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->postJson("/events/{$event->id}/flights/{$flight->id}/sync")->assertOk();

        return [$response->json(), $flight->refresh()];
    }

    public function test_a_past_flight_takes_oags_actual_times(): void
    {
        [$json, $flight] = $this->sync($this->oagFlight([[
            'state' => 'InGate',
            'departure' => [
                'actualTime' => ['outGate' => ['local' => '2026-10-28T16:17:00+03:00'], 'outGateVariation' => '-00:03:00'],
                'gate' => '14A',
            ],
            'arrival' => [
                'estimatedTime' => ['inGate' => ['local' => '2026-10-28T17:08:00+03:00']],
                'actualTime' => ['inGate' => ['local' => '2026-10-28T17:41:00+03:00'], 'inGateVariation' => '00:21:00'],
            ],
        ]]));

        $this->assertSame('oag', $json['source']);
        $this->assertSame('landed', $json['flight_status']);
        $this->assertSame('2026-10-28T17:41', $json['arrival']['actual']);
        $this->assertSame('2026-10-28T16:17', $json['departure']['actual']);
        $this->assertSame('14A', $json['departure']['gate']);

        $this->assertSame('landed', $flight->flight_status);
        $this->assertSame('2026-10-28 17:41', $flight->actual_at->format('Y-m-d H:i'), 'venue wall clock, offset dropped');
        $this->assertSame('2026-10-28 17:08', $flight->estimated_at->format('Y-m-d H:i'));
        $this->assertSame(21, $flight->delay_minutes);
    }

    public function test_a_flight_without_status_yet_keeps_only_its_schedule(): void
    {
        [$json, $flight] = $this->sync($this->oagFlight([]));

        $this->assertNull($json['flight_status']);
        $this->assertNull($json['arrival']['actual']);
        $this->assertSame('2026-10-28 17:20', $json['arrival']['scheduled']);
        $this->assertNull($flight->flight_status);
        $this->assertNull($flight->actual_at);
        $this->assertNotNull($flight->flight_synced_at);
    }
}
