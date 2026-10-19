<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\CheckpointTemplate;
use App\Models\Event;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\MovementTemplate;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamFlight;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class BusMovementTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    private User $admin;

    private MovementTemplate $template;

    private Checkpoint $meet;

    private Checkpoint $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->event = $this->createEvent();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');

        $this->meet = Checkpoint::create(['code' => 'CP-MEET', 'name' => 'Meet the team', 'type' => 'manual', 'is_active' => true]);
        $this->hotel = Checkpoint::create(['code' => 'CP-HOTEL', 'name' => 'Arrive at hotel', 'type' => 'manual', 'is_active' => true]);
        $sequence = CheckpointTemplate::create(['event_id' => $this->event->id, 'code' => 'CT-ARR', 'name' => 'Arrival', 'movement_type' => 'arrival', 'is_active' => true]);
        $sequence->checkpoints()->attach($this->meet->id, ['order' => 1, 'is_required' => true]);
        $sequence->checkpoints()->attach($this->hotel->id, ['order' => 2, 'is_required' => true]);

        $this->template = MovementTemplate::create(['event_id' => $this->event->id, 'code' => 'MVT-ARR', 'name' => 'Team Arrival', 'scenario_type' => 'arrival_day', 'is_active' => true]);
        $this->template->legs()->create(['order' => 1, 'leg_type' => 'arrival', 'checkpoint_template_id' => $sequence->id, 'estimated_duration_minutes' => 60,
            'from_location' => 'Abu Samra border', 'to_location' => 'Team hotel']);

        // Flight arrivals: the window starts 3 h before landing, the hotel step 1 h after.
        $settings = app(SettingsService::class);
        $settings->setSetting('movement_offset.arrival', -180, Setting::SCOPE_GLOBAL);
        $settings->setSetting('movement_offset.arrival', -180, Setting::SCOPE_GLOBAL, null, null, $this->meet->id);
        $settings->setSetting('movement_offset.arrival', 60, Setting::SCOPE_GLOBAL, null, null, $this->hotel->id);
    }

    private function teamArrivingBy(string $number, string $at): Team
    {
        $team = $this->createTeam($this->event);
        TeamFlight::create(['event_id' => $this->event->id, 'team_id' => $team->id, 'direction' => 'arrival', 'flight_number' => $number, 'scheduled_at' => $at, 'party_size_total' => 30]);

        return $team;
    }

    private function bulkPlan(Team ...$teams): void
    {
        $this->actingAs($this->admin)->withSession(['active_event_id' => $this->event->id])
            ->post('/plans/bulk', ['plans' => [[
                'date' => '2026-11-16',
                'movement_template_id' => $this->template->id,
                'teams' => array_map(fn (Team $team) => ['team_id' => $team->id, 'start_time' => '09:00'], $teams),
            ]]])
            ->assertSessionHasNoErrors();
    }

    private function movementOf(Team $team): Movement
    {
        return Movement::where('team_id', $team->id)->firstOrFail();
    }

    public function test_a_bus_arrival_is_timed_from_the_bus_and_uses_the_arrival_offsets_until_it_has_its_own(): void
    {
        $bus = $this->teamArrivingBy('BUS', '2026-11-16 10:00:00');
        $this->bulkPlan($bus);

        $movement = $this->movementOf($bus);
        $this->assertTrue($movement->isBusMovement());
        $this->assertSame('bus_arrival', $movement->offsetType());
        $this->assertSame('2026-11-16 07:00', $movement->window_start->format('Y-m-d H:i'));
        $this->assertSame('2026-11-16 08:00', $movement->window_end->format('Y-m-d H:i'));

        $movement->update(['vehicle_id' => $this->createVehicle($this->event)->id, 'driver_id' => $this->createDriver($this->event)->id,
            'field_supervisor_id' => $this->createProviderUser('ground_control')->id]);

        $this->post("/plans/{$movement->plan_id}/generate-jobs", ['movement_ids' => [$movement->id]])
            ->assertSessionHas('success', '1 jobs generated successfully');

        $job = JobOperation::where('movement_id', $movement->id)->firstOrFail();
        $times = $job->checkpoints()->orderBy('order')->get()->map(fn ($cp) => $cp->scheduled_at?->format('H:i'))->all();
        $this->assertSame(['07:00', '11:00'], $times);
    }

    public function test_bus_offsets_apply_to_road_legs_only(): void
    {
        $settings = app(SettingsService::class);
        $settings->setSetting('movement_offset.bus_arrival', -60, Setting::SCOPE_GLOBAL);
        $settings->setSetting('movement_offset.bus_arrival', -60, Setting::SCOPE_GLOBAL, null, null, $this->meet->id);

        $bus = $this->teamArrivingBy('BUS', '2026-11-16 10:00:00');
        $flight = $this->teamArrivingBy('QR 100', '2026-11-16 10:00:00');
        $this->bulkPlan($bus, $flight);

        $this->assertSame('09:00', $this->movementOf($bus)->window_start->format('H:i'));
        $this->assertSame('07:00', $this->movementOf($flight)->window_start->format('H:i'));

        // The hotel step has no bus-specific offset, so it keeps the arrival one.
        $this->assertSame(60, $settings->getCheckpointOffset($this->hotel->id, 'bus_arrival', $this->event->id));
    }

    public function test_saving_a_bus_offset_in_settings_re_times_only_road_legs(): void
    {
        $bus = $this->teamArrivingBy('BUS', '2026-11-16 10:00:00');
        $flight = $this->teamArrivingBy('QR 100', '2026-11-16 10:00:00');
        $this->bulkPlan($bus, $flight);

        $this->post('/setups/settings/event', [
            'event_id' => $this->event->id, 'movement_type' => 'bus_arrival', 'value' => -30, 'checkpoint_id' => $this->meet->id,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('09:30', $this->movementOf($bus)->window_start->format('H:i'));
        $this->assertSame('07:00', $this->movementOf($flight)->window_start->format('H:i'));

        $this->postJson('/setups/settings/preview-impact', ['movement_type' => 'bus_arrival', 'scope' => 'event', 'event_id' => $this->event->id])
            ->assertJson(['count' => 1]);
    }
}
