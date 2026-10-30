<?php

namespace Tests\Feature;

use App\Models\Airport;
use App\Models\Country;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamFlight;
use App\Models\TeamStay;
use App\Models\TeamTraining;
use App\Models\User;
use App\Services\SettingsService;
use Carbon\Carbon;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Event Teams "Add a team" / "Edit team" dialog (TeamProfileController): team, flights, hotel and trainings in one save. */
class TeamProfileTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    private string $country;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->event = $this->createEvent();
        $this->country = Country::firstOrCreate(['country_code' => 'KWT'], ['country_name' => 'Kuwait'])->country_code;
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    private function leg(array $values = []): array
    {
        return $values + ['id' => null, 'date' => '', 'time' => '', 'flight_number' => '', 'origin_airport_id' => null, 'destination_airport_id' => null, 'status' => 'auto', 'actual_time' => '', 'gate' => null, 'notes' => null, 'remove' => false];
    }

    private function payload(array $team = [], array $rest = []): array
    {
        return $rest + [
            'team' => $team + ['code' => 'KUW-17', 'team_name' => 'Kuwait', 'country_id' => $this->country, 'group_pool' => 'A1', 'role' => 'team'],
            'arrival' => $this->leg(),
            'departure' => $this->leg(),
            'hotel_name' => null,
            'party' => null,
            'trainings' => [],
        ];
    }

    public function test_adding_a_team_saves_its_flights_hotel_and_training_sessions(): void
    {
        $this->actingAs($this->admin())
            ->post("/events/{$this->event->id}/teams/profile", $this->payload(['role' => 'guest'], [
                'arrival' => $this->leg(['date' => '2026-11-14', 'time' => '06:20', 'flight_number' => 'ku 101', 'status' => 'delayed', 'actual_time' => '07:05']),
                'departure' => $this->leg(['date' => '2026-11-22', 'time' => '18:45']),
                'hotel_name' => 'Al Aziziyah Boutique',
                'trainings' => [
                    ['id' => null, 'training_ground' => 'Al Sadd TC', 'start_at' => '2026-11-15 16:00', 'notes' => 'bus 15:15'],
                    ['id' => null, 'training_ground' => null, 'start_at' => '2026-11-16 10:00', 'notes' => null],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Team added.');

        $team = Team::where('event_id', $this->event->id)->where('code', 'KUW-17')->firstOrFail();
        $this->assertSame('guest', $team->role);
        $this->assertSame('A1', $team->group_pool);

        $arrival = TeamFlight::where('team_id', $team->id)->where('direction', 'arrival')->firstOrFail();
        $this->assertSame('KU 101', $arrival->flight_number);
        $this->assertSame('2026-11-14 06:20', $arrival->scheduled_at->format('Y-m-d H:i'));
        $this->assertSame('2026-11-14 07:05', $arrival->estimated_at->format('Y-m-d H:i'));
        $this->assertSame(45, $arrival->delay_minutes);

        $departure = TeamFlight::where('team_id', $team->id)->where('direction', 'departure')->firstOrFail();
        $this->assertNull($departure->flight_number, 'a blank flight number is TBC');

        $stay = TeamStay::where('team_id', $team->id)->firstOrFail();
        $this->assertSame('Al Aziziyah Boutique', $stay->hotel_name);
        $this->assertSame('2026-11-14', Carbon::parse($stay->check_in)->toDateString());
        $this->assertSame('2026-11-22', Carbon::parse($stay->check_out)->toDateString());

        $this->assertSame(2, TeamTraining::where('team_id', $team->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Team added']);
    }

    public function test_a_host_team_needs_no_flights(): void
    {
        $this->actingAs($this->admin())
            ->post("/events/{$this->event->id}/teams/profile", $this->payload(['code' => 'QAT-17', 'team_name' => 'Qatar', 'role' => 'host'], [
                'arrival' => null,
                'departure' => null,
            ]))
            ->assertSessionHasNoErrors();

        $team = Team::where('code', 'QAT-17')->firstOrFail();
        $this->assertSame('host', $team->role);
        $this->assertSame(0, TeamFlight::where('team_id', $team->id)->count());
    }

    public function test_editing_a_flight_time_re_times_planned_movements_and_reports_jobs(): void
    {
        $team = $this->createTeam($this->event);
        $flight = TeamFlight::create([
            'event_id' => $this->event->id, 'team_id' => $team->id, 'direction' => 'arrival',
            'flight_number' => 'QR 194', 'scheduled_at' => '2026-11-16 23:05:00',
        ]);
        $plan = tap($this->createPlan($this->event))->update(['date' => '2026-11-16']);
        $movement = fn (array $extra = []) => $this->createMovement($this->event, $plan, $team, $extra + [
            'kind' => 'arrival', 'flight_id' => $flight->id, 'flight_number' => 'QR 194',
            'window_start' => '2026-11-16 23:05:00', 'window_end' => '2026-11-16 23:45:00',
        ]);
        $planned = $movement();
        $issued = $movement(['job_id' => 'JOB-TEST-0001']);

        $this->actingAs($this->admin())
            ->put("/events/{$this->event->id}/teams/{$team->code}/profile", $this->payload(
                ['code' => $team->code, 'team_name' => $team->team_name],
                ['arrival' => $this->leg(['id' => $flight->id, 'date' => '2026-11-16', 'time' => '21:30', 'flight_number' => 'QR 194'])],
            ))
            ->assertRedirect()
            ->assertSessionHas('success', 'Team updated. 1 planned movement updated. 1 movement already has a job and must be updated by hand.')
            ->assertSessionHas('flight_change', fn ($change) => collect($change['movements'])->pluck('status', 'code')->all() === [
                $planned->code => 'updated',
                $issued->code => 'needs_review',
            ]);

        $offset = app(SettingsService::class)->getMovementOffset('arrival', $this->event->id);
        $this->assertTrue(Carbon::parse('2026-11-16 21:30')->addMinutes($offset)->equalTo($planned->refresh()->window_start));
        $this->assertSame('2026-11-16 23:05', $issued->refresh()->window_start->format('Y-m-d H:i'));
    }

    public function test_a_landed_time_past_midnight_is_the_next_day(): void
    {
        $team = $this->createTeam($this->event);
        $flight = TeamFlight::create([
            'event_id' => $this->event->id, 'team_id' => $team->id, 'direction' => 'arrival',
            'flight_number' => 'QR 8', 'scheduled_at' => '2026-11-16 23:50:00',
        ]);

        $this->actingAs($this->admin())
            ->put("/events/{$this->event->id}/teams/{$team->code}/profile", $this->payload(
                ['code' => $team->code, 'team_name' => $team->team_name],
                ['arrival' => $this->leg(['id' => $flight->id, 'date' => '2026-11-16', 'time' => '23:50', 'flight_number' => 'QR 8', 'status' => 'landed', 'actual_time' => '00:20'])],
            ))
            ->assertSessionHasNoErrors();

        $flight->refresh();
        $this->assertSame('landed', $flight->flight_status);
        $this->assertSame('2026-11-17 00:20', $flight->actual_at->format('Y-m-d H:i'));
        $this->assertSame(30, $flight->delay_minutes);
    }

    public function test_the_party_applies_to_both_flights_and_each_flight_keeps_its_gate_and_notes(): void
    {
        $team = $this->createTeam($this->event);
        $arrival = TeamFlight::create(['event_id' => $this->event->id, 'team_id' => $team->id, 'direction' => 'arrival', 'flight_number' => 'KU 101', 'scheduled_at' => '2026-11-14 06:20:00', 'party_size_total' => 30]);
        $departure = TeamFlight::create(['event_id' => $this->event->id, 'team_id' => $team->id, 'direction' => 'departure', 'flight_number' => 'KU 102', 'scheduled_at' => '2026-11-22 18:45:00', 'party_size_total' => 28]);
        $plan = tap($this->createPlan($this->event))->update(['date' => '2026-11-14']);
        $movement = $this->createMovement($this->event, $plan, $team, [
            'kind' => 'arrival', 'flight_id' => $arrival->id, 'flight_number' => 'KU 101', 'passengers' => 30,
            'window_start' => '2026-11-14 06:20:00', 'window_end' => '2026-11-14 07:00:00',
        ]);
        $legs = fn (array $extra = []) => [
            'arrival' => $this->leg(['id' => $arrival->id, 'date' => '2026-11-14', 'time' => '06:20', 'flight_number' => 'KU 101', 'gate' => 'C12', 'notes' => 'Meet at Al Maha'] + $extra),
            'departure' => $this->leg(['id' => $departure->id, 'date' => '2026-11-22', 'time' => '18:45', 'flight_number' => 'KU 102']),
        ];
        $put = fn (array $rest) => $this->actingAs($this->admin())
            ->put("/events/{$this->event->id}/teams/{$team->code}/profile", $this->payload(['code' => $team->code, 'team_name' => $team->team_name], $rest));

        // An untouched party (null) leaves the two flights' own figures alone.
        $put($legs())->assertSessionHasNoErrors();
        $this->assertSame(30, $arrival->refresh()->party_size_total);
        $this->assertSame(28, $departure->refresh()->party_size_total);
        $this->assertSame('C12', $arrival->gate);
        $this->assertSame('Meet at Al Maha', $arrival->notes);
        $this->assertNull($departure->gate);

        $put($legs() + ['party' => ['players' => 23, 'staff' => 12, 'bags' => 80]])
            ->assertSessionHas('success', 'Team updated. 1 planned movement updated.');
        foreach ([$arrival->refresh(), $departure->refresh()] as $flight) {
            $this->assertSame([23, 12, 35, 80], [$flight->party_size_players, $flight->party_size_staff, $flight->party_size_total, $flight->planned_bags]);
        }
        $this->assertSame(35, $movement->refresh()->passengers, 'the new party size reaches the planned movement');
    }

    public function test_both_airports_can_be_set_and_a_new_flights_home_airport_defaults_to_the_events_usual_one(): void
    {
        $doh = Airport::create(['code' => 'DOH', 'name' => 'Hamad International']);
        $kwi = Airport::create(['code' => 'KWI', 'name' => 'Kuwait International']);
        $dia = Airport::create(['code' => 'DIA', 'name' => 'Doha International']);
        $other = $this->createTeam($this->event);
        TeamFlight::create(['event_id' => $this->event->id, 'team_id' => $other->id, 'direction' => 'arrival', 'scheduled_at' => '2026-11-13 10:00:00', 'destination_airport_id' => $doh->id]);

        $this->actingAs($this->admin())
            ->post("/events/{$this->event->id}/teams/profile", $this->payload([], [
                'arrival' => $this->leg(['date' => '2026-11-14', 'time' => '06:20', 'flight_number' => 'KU 101', 'origin_airport_id' => $kwi->id]),
                'departure' => $this->leg(['date' => '2026-11-22', 'time' => '18:45', 'flight_number' => 'KU 102', 'origin_airport_id' => $dia->id, 'destination_airport_id' => $kwi->id]),
            ]))
            ->assertSessionHasNoErrors();

        $team = Team::where('code', 'KUW-17')->firstOrFail();
        $arrival = TeamFlight::where('team_id', $team->id)->where('direction', 'arrival')->firstOrFail();
        $this->assertSame([$kwi->id, $doh->id], [$arrival->origin_airport_id, $arrival->destination_airport_id], 'the home end left on Auto takes the usual airport');
        $departure = TeamFlight::where('team_id', $team->id)->where('direction', 'departure')->firstOrFail();
        $this->assertSame([$dia->id, $kwi->id], [$departure->origin_airport_id, $departure->destination_airport_id], 'a chosen home airport is kept');

        // Editing changes either end.
        $this->actingAs($this->admin())
            ->put("/events/{$this->event->id}/teams/{$team->code}/profile", $this->payload([], [
                'arrival' => $this->leg(['id' => $arrival->id, 'date' => '2026-11-14', 'time' => '06:20', 'flight_number' => 'KU 101', 'origin_airport_id' => $kwi->id, 'destination_airport_id' => $dia->id]),
            ]))
            ->assertSessionHasNoErrors();
        $this->assertSame($dia->id, $arrival->refresh()->destination_airport_id);
    }

    public function test_removing_a_flight_clears_its_planned_movements_and_blocks_their_jobs(): void
    {
        $team = $this->createTeam($this->event);
        $arrival = TeamFlight::create(['event_id' => $this->event->id, 'team_id' => $team->id, 'direction' => 'arrival', 'flight_number' => 'QR 194', 'scheduled_at' => '2026-11-16 23:05:00']);
        $plan = tap($this->createPlan($this->event))->update(['date' => '2026-11-16']);
        $planned = $this->createMovement($this->event, $plan, $team, [
            'kind' => 'arrival', 'flight_id' => $arrival->id, 'flight_number' => 'QR 194',
            'window_start' => '2026-11-16 23:05:00', 'window_end' => '2026-11-16 23:45:00',
        ]);

        $this->actingAs($this->admin())
            ->put("/events/{$this->event->id}/teams/{$team->code}/profile", $this->payload(
                ['code' => $team->code, 'team_name' => $team->team_name],
                ['arrival' => $this->leg(['id' => $arrival->id, 'date' => '2026-11-16', 'time' => '23:05', 'flight_number' => 'QR 194', 'remove' => true])],
            ))
            ->assertSessionHas('success', 'Team updated. 1 planned movement lost its flight and times: no job can be generated until the flight is added again.');

        $this->assertDatabaseMissing('team_flights', ['id' => $arrival->id]);
        $planned->refresh();
        $this->assertNull($planned->flight_id);
        $this->assertNull($planned->window_start);
        $this->assertNull($planned->window_end);
        $this->assertNull($planned->flight_number);
        $this->assertNotNull($planned->jobBlocker());
        $this->assertDatabaseHas('audit_logs', ['action' => 'Team updated']);

        // Generating is refused for the movement that lost its flight.
        $this->actingAs($this->admin())
            ->withSession(['active_event_id' => $this->event->id])
            ->post("/plans/{$plan->id}/generate-jobs", ['movement_ids' => [$planned->id]])
            ->assertSessionHas('error', "No arrival flight: add the team's arrival in Event Teams first.");
        $this->assertNull($planned->refresh()->job_id);

        // Adding the arrival again links the movement back and times it from the new flight.
        $this->actingAs($this->admin())
            ->put("/events/{$this->event->id}/teams/{$team->code}/profile", $this->payload(
                ['code' => $team->code, 'team_name' => $team->team_name],
                ['arrival' => $this->leg(['date' => '2026-11-16', 'time' => '21:30', 'flight_number' => 'QR 196'])],
            ))
            ->assertSessionHas('success', 'Team updated. 1 planned movement updated.');
        $planned->refresh();
        $flight = TeamFlight::where('team_id', $team->id)->where('direction', 'arrival')->firstOrFail();
        $this->assertSame($flight->id, $planned->flight_id);
        $this->assertSame('QR 196', $planned->flight_number);
        $offset = app(SettingsService::class)->getMovementOffset('arrival', $this->event->id);
        $this->assertTrue(Carbon::parse('2026-11-16 21:30')->addMinutes($offset)->equalTo($planned->window_start));
        $this->assertNull($planned->jobBlocker());
    }

    public function test_a_flight_a_job_was_generated_from_cannot_be_removed(): void
    {
        $team = $this->createTeam($this->event);
        $arrival = TeamFlight::create(['event_id' => $this->event->id, 'team_id' => $team->id, 'direction' => 'arrival', 'flight_number' => 'BUS', 'scheduled_at' => '2026-11-16 23:05:00']);
        $plan = tap($this->createPlan($this->event))->update(['date' => '2026-11-16']);
        $attributes = [
            'kind' => 'arrival', 'flight_id' => $arrival->id, 'flight_number' => 'BUS',
            'window_start' => '2026-11-16 23:05:00', 'window_end' => '2026-11-16 23:45:00',
        ];
        $planned = $this->createMovement($this->event, $plan, $team, $attributes);
        $this->createMovement($this->event, $plan, $team, $attributes + ['job_id' => 'JOB-TEST-0001']);

        // The dialog sees it, so it can lock the bin.
        $this->actingAs($this->admin())->getJson("/events/{$this->event->id}/teams/{$team->id}/profile")
            ->assertJsonPath('team.flights.0.issued_jobs_count', 1);

        // Refused, and nothing else in the same save is kept.
        $this->actingAs($this->admin())
            ->put("/events/{$this->event->id}/teams/{$team->code}/profile", $this->payload(
                ['code' => $team->code, 'team_name' => $team->team_name],
                [
                    'hotel_name' => 'Should not be saved',
                    'arrival' => $this->leg(['id' => $arrival->id, 'date' => '2026-11-16', 'time' => '23:05', 'flight_number' => 'BUS', 'remove' => true]),
                ],
            ))
            ->assertSessionHasErrors(['arrival.remove' => "Arrival BUS can't be removed: job JOB-TEST-0001 was generated from it. Delete that job in the Jobs Queue first, or change the flight instead."]);

        $this->assertDatabaseHas('team_flights', ['id' => $arrival->id]);
        $this->assertSame($arrival->id, $planned->refresh()->flight_id);
        $this->assertSame('2026-11-16 23:05', $planned->window_start->format('Y-m-d H:i'));
        $this->assertDatabaseMissing('team_stays', ['hotel_name' => 'Should not be saved']);

        // The old delete route follows the same rule.
        $this->actingAs($this->admin())
            ->delete("/events/{$this->event->id}/flights/{$arrival->id}")
            ->assertSessionHasErrors('flight');
        $this->assertDatabaseHas('team_flights', ['id' => $arrival->id]);
    }

    public function test_auto_hands_the_status_back_to_the_flight_source_and_an_untouched_status_is_kept(): void
    {
        $team = $this->createTeam($this->event);
        $flight = TeamFlight::create([
            'event_id' => $this->event->id, 'team_id' => $team->id, 'direction' => 'arrival', 'flight_number' => 'QR 8',
            'scheduled_at' => '2026-11-16 10:00:00', 'flight_status' => 'landed', 'actual_at' => '2026-11-16 10:20:00', 'delay_minutes' => 20,
        ]);
        $put = fn (?string $status) => $this->actingAs($this->admin())
            ->put("/events/{$this->event->id}/teams/{$team->code}/profile", $this->payload(
                ['code' => $team->code, 'team_name' => $team->team_name],
                ['arrival' => $this->leg(['id' => $flight->id, 'date' => '2026-11-16', 'time' => '10:00', 'flight_number' => 'QR 8', 'status' => $status, 'actual_time' => '10:20'])],
            ))->assertSessionHasNoErrors();

        $put('auto');
        $flight->refresh();
        $this->assertNull($flight->flight_status);
        $this->assertNull($flight->actual_at);
        $this->assertNull($flight->delay_minutes);

        // OAG's live status (in the air, with an ETA) survives a save that didn't touch the status.
        $flight->update(['flight_status' => 'active', 'estimated_at' => '2026-11-16 10:35:00', 'delay_minutes' => 35]);
        $put(null);
        $flight->refresh();
        $this->assertSame('active', $flight->flight_status);
        $this->assertSame('2026-11-16 10:35', $flight->estimated_at->format('Y-m-d H:i'));
    }

    public function test_the_sessions_sent_replace_the_teams_sessions(): void
    {
        $team = $this->createTeam($this->event);
        $kept = TeamTraining::create(['event_id' => $this->event->id, 'team_id' => $team->id, 'training_ground' => 'Old', 'training_start_at' => '2026-11-15 16:00:00']);
        $dropped = TeamTraining::create(['event_id' => $this->event->id, 'team_id' => $team->id, 'training_ground' => 'Gone', 'training_start_at' => '2026-11-16 16:00:00']);

        $this->actingAs($this->admin())
            ->put("/events/{$this->event->id}/teams/{$team->code}/profile", $this->payload(
                ['code' => $team->code, 'team_name' => $team->team_name],
                ['trainings' => [
                    ['id' => $kept->id, 'training_ground' => 'Aspire 4', 'start_at' => '2026-11-15 17:00', 'notes' => null],
                    ['id' => null, 'training_ground' => 'Aspire 6', 'start_at' => '2026-11-17 09:00', 'notes' => null],
                ]],
            ))
            ->assertSessionHasNoErrors();

        $this->assertSame('Aspire 4', $kept->refresh()->training_ground);
        $this->assertSame('2026-11-15 17:00', Carbon::parse($kept->training_start_at)->format('Y-m-d H:i'));
        $this->assertDatabaseMissing('team_trainings', ['id' => $dropped->id]);
        $this->assertSame(['Aspire 4', 'Aspire 6'], TeamTraining::where('team_id', $team->id)->orderBy('training_start_at')->pluck('training_ground')->all());
    }

    public function test_another_teams_flight_or_session_and_an_unknown_role_are_refused(): void
    {
        $team = $this->createTeam($this->event);
        $other = $this->createTeam($this->event);
        $foreignFlight = TeamFlight::create(['event_id' => $this->event->id, 'team_id' => $other->id, 'direction' => 'arrival', 'scheduled_at' => '2026-11-16 10:00:00']);
        $foreignSession = TeamTraining::create(['event_id' => $this->event->id, 'team_id' => $other->id, 'training_start_at' => '2026-11-16 16:00:00']);

        $this->actingAs($this->admin())
            ->put("/events/{$this->event->id}/teams/{$team->code}/profile", $this->payload(
                ['code' => $team->code, 'team_name' => $team->team_name, 'country_id' => null, 'role' => 'sponsor'],
                [
                    'arrival' => $this->leg(['id' => $foreignFlight->id, 'date' => '2026-11-16', 'time' => '11:00']),
                    'trainings' => [['id' => $foreignSession->id, 'training_ground' => null, 'start_at' => '2026-11-16 17:00', 'notes' => null]],
                ],
            ))
            ->assertSessionHasErrors(['team.country_id', 'team.role', 'arrival.id', 'trainings.0.id']);

        $this->assertSame('2026-11-16 10:00', $foreignFlight->refresh()->scheduled_at->format('Y-m-d H:i'));
    }

    public function test_the_dialog_can_load_a_team_for_planning(): void
    {
        $team = $this->createTeam($this->event);
        TeamFlight::create(['event_id' => $this->event->id, 'team_id' => $team->id, 'direction' => 'arrival', 'flight_number' => 'QR 8', 'scheduled_at' => '2026-11-16 10:00:00']);
        $other = $this->createTeam($this->createEvent());

        $this->actingAs($this->admin())
            ->getJson("/events/{$this->event->id}/teams/{$team->id}/profile")
            ->assertOk()
            ->assertJsonPath('team.code', $team->code)
            ->assertJsonPath('team.flights.0.flight_number', 'QR 8')
            ->assertJsonStructure(['airports', 'countries', 'classifications', 'baseCampHotels', 'groupOptions']);

        // Another event's team isn't reachable through this event.
        $this->actingAs($this->admin())->getJson("/events/{$this->event->id}/teams/{$other->id}/profile")->assertNotFound();

        $viewer = User::factory()->create();
        $viewer->givePermissionTo('events.view');
        $this->actingAs($viewer)->getJson("/events/{$this->event->id}/teams/{$team->id}/profile")->assertForbidden();
    }

    public function test_it_needs_events_manage(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo('events.view');

        $this->actingAs($viewer)
            ->post("/events/{$this->event->id}/teams/profile", $this->payload())
            ->assertForbidden();
        $this->assertSame(0, Team::where('code', 'KUW-17')->count());
    }
}
