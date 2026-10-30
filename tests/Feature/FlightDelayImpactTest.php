<?php

namespace Tests\Feature;

use App\Ai\Agents\OperationsCopilotAgent;
use App\Ai\Tools\GetFlightDelayImpactTool;
use App\Models\Event;
use App\Models\Movement;
use App\Models\TeamFlight;
use App\Models\User;
use App\Services\Ai\FlightDelayImpact;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** What a late flight puts at risk: a what-if on the conflict rules, nothing saved. */
class FlightDelayImpactTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    private TeamFlight $flight;

    private Movement $arrival;

    private Movement $next;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Carbon::setTestNow('2026-11-20 08:00:00');
        $this->event = $this->createEvent();
        session(['active_event_id' => $this->event->id]);

        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $bus = $this->createVehicle($this->event, ['code' => 'BUS-1']);
        $driver = $this->createDriver($this->event, ['name' => 'Driver One']);
        $this->flight = TeamFlight::create([
            'event_id' => $this->event->id, 'team_id' => $team->id, 'direction' => 'arrival',
            'flight_number' => 'QR123', 'scheduled_at' => '2026-11-20 10:00:00',
        ]);
        // The arrival transfer, then the same bus and driver on another team's transfer at 12:00:
        // 30 minutes apart today; a 45-minute delay makes them overlap.
        $this->arrival = $this->createMovement($this->event, $plan, $team, [
            'kind' => 'arrival', 'flight_id' => $this->flight->id, 'from_location' => 'Airport', 'to_location' => 'Hotel',
            'window_start' => '2026-11-20 10:30', 'window_end' => '2026-11-20 11:30',
            'vehicle_id' => $bus->id, 'driver_id' => $driver->id,
        ]);
        $this->next = $this->createMovement($this->event, $plan, $this->createTeam($this->event), [
            'window_start' => '2026-11-20 12:00', 'window_end' => '2026-11-20 13:00',
            'vehicle_id' => $bus->id, 'driver_id' => $driver->id,
        ]);
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

    public function test_a_delay_reports_the_double_booking_it_causes_and_saves_nothing(): void
    {
        $result = app(FlightDelayImpact::class)->build($this->event->id, $this->admin(), $this->flight, 45);

        $this->assertSame('Fri 20 Nov 10:45', $result['flight']['assumed']);
        $this->assertSame('Fri 11:15–12:15', $result['moved_movements'][0]['window_if_late']);
        $types = collect($result['new_risks'])->pluck('type')->all();
        $this->assertContains('Vehicle Double-Booked', $types);
        $this->assertContains('Driver Double-Booked', $types);
        $this->assertGreaterThan(0, $result['newly_at_risk']);

        // Nothing changed in the database.
        $this->assertSame('2026-11-20 10:30', $this->arrival->refresh()->window_start->format('Y-m-d H:i'));
        $this->assertSame('2026-11-20 10:00', $this->flight->refresh()->scheduled_at->format('Y-m-d H:i'));

        // A short delay that keeps the gap brings nothing new.
        $small = app(FlightDelayImpact::class)->build($this->event->id, $this->admin(), $this->flight, 5);
        $this->assertNotContains('Vehicle Double-Booked', collect($small['new_risks'])->pluck('type')->all());
    }

    public function test_the_tool_uses_the_live_delay_and_asks_when_there_is_none(): void
    {
        $this->actingAs($this->admin());
        $tool = app(GetFlightDelayImpactTool::class);

        $none = json_decode($tool->handle(new Request(['flight_number' => 'QR 123'])), true);
        $this->assertStringContainsString('no known delay', $none['error']);

        $this->flight->update(['estimated_at' => '2026-11-20 10:45:00']);
        $live = json_decode($tool->handle(new Request(['flight_number' => 'qr123'])), true);
        $this->assertSame(45, $live['flight']['delay_minutes']);
        $this->assertContains('Vehicle Double-Booked', collect($live['new_risks'])->pluck('type')->all());

        $this->assertArrayHasKey('error', json_decode($tool->handle(new Request(['flight_number' => 'XX1', 'delay_minutes' => 30])), true));
    }

    public function test_the_event_teams_button_sends_the_flight_as_context(): void
    {
        OperationsCopilotAgent::fake(['ok']);
        $admin = $this->admin();

        $this->actingAs($admin)->withSession(['active_event_id' => $this->event->id])
            ->postJson('/ai/query', ['question' => 'QR123 is 30 min late: what is now at risk?', 'context' => ['page' => 'event-teams', 'flight_id' => $this->flight->id]])
            ->assertOk();

        OperationsCopilotAgent::assertPrompted(fn ($prompt) => str_contains($prompt->agent->instructions(), 'Event Teams page, flight QR123'));
        $this->assertContains(GetFlightDelayImpactTool::class, collect((new OperationsCopilotAgent)->tools())->map(fn ($t) => $t::class)->all());
    }
}
