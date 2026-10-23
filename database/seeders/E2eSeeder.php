<?php

namespace Database\Seeders;

use App\Models\Checkpoint;
use App\Models\CheckpointTemplate;
use App\Models\Country;
use App\Models\Driver;
use App\Models\Event;
use App\Models\FleetProvider;
use App\Models\GameMatch;
use App\Models\Movement;
use App\Models\MovementTemplate;
use App\Models\Plan;
use App\Models\RosterMember;
use App\Models\Setting;
use App\Models\Team;
use App\Models\TeamFlight;
use App\Models\TeamStay;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Venue;
use App\Services\SettingsService;
use Illuminate\Database\Seeder;

/**
 * Small, fixed data set for the browser smoke tests (tests/e2e), run against the
 * lmsx_e2e database only. Sign in as e2e.admin@example.test / e2e-password.
 */
class E2eSeeder extends Seeder
{
    public const ADMIN_EMAIL = 'e2e.admin@example.test';

    public const ADMIN_PASSWORD = 'e2e-password';

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        $provider = FleetProvider::withoutGlobalScopes()->create(['code' => 'GWC', 'name' => 'GWC']);
        $day = now()->addDays(3)->startOfDay();

        $event = Event::create([
            'name' => 'E2E Cup', 'short_name' => 'E2E', 'active_flag' => true,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDays(30)->toDateString(),
            'fleet_provider_id' => $provider->id,
        ]);

        $admin = User::factory()->create(['name' => 'E2E Admin', 'email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]);
        $admin->assignRole('admin');
        $admin->events()->attach($event);

        $supervisor = User::factory()->create(['name' => 'E2E Supervisor', 'email' => 'e2e.supervisor@example.test', 'fleet_provider_id' => $provider->id]);
        $supervisor->assignRole('ground_control');
        $supervisor->events()->attach($event);
        RosterMember::create(['event_id' => $event->id, 'user_id' => $supervisor->id, 'sort' => 1]);

        $venue = Venue::create(['name' => 'E2E Stadium', 'type' => 'stadium']);
        $event->venues()->attach($venue);

        $teams = collect([['QAT', 'Qatar'], ['CHN', 'China'], ['UAE', 'United Arab Emirates'], ['BHR', 'Bahrain']])
            ->mapWithKeys(function ($row, $i) use ($event, $day) {
                [$code, $name] = $row;
                Country::firstOrCreate(['country_code' => $code], ['country_name' => $name]);
                $team = Team::create(['event_id' => $event->id, 'code' => "{$code}-17", 'team_name' => $name, 'country_id' => $code, 'is_active' => true]);
                TeamFlight::create([
                    'event_id' => $event->id, 'team_id' => $team->id, 'direction' => 'arrival',
                    'flight_number' => 'QR'.(100 + $i), 'scheduled_at' => $day->copy()->subDays(2)->setTime(10 + $i, 0),
                ]);
                // Two team hotels for the Duty Roster's hotel schedule (no dates, so no stay conflicts).
                TeamStay::create(['event_id' => $event->id, 'team_id' => $team->id, 'hotel_name' => $i < 2 ? 'E2E Grand Hotel' : 'E2E Bay Hotel']);

                return [$code => $team];
            });

        GameMatch::create(['event_id' => $event->id, 'venue_id' => $venue->id, 'match_number' => 'E2E-001', 'team1_id' => $teams['QAT']->id, 'team2_id' => $teams['CHN']->id, 'kick_off' => $day->copy()->setTime(18, 0), 'match_date' => $day->toDateString()]);
        GameMatch::create(['event_id' => $event->id, 'venue_id' => $venue->id, 'match_number' => 'E2E-002', 'team1_id' => $teams['UAE']->id, 'team2_id' => $teams['BHR']->id, 'kick_off' => $day->copy()->setTime(21, 0), 'match_date' => $day->toDateString()]);
        GameMatch::create(['event_id' => $event->id, 'venue_id' => $venue->id, 'match_number' => 'E2E-003', 'team1_id' => $teams['QAT']->id, 'team2_id' => $teams['UAE']->id, 'kick_off' => $day->copy()->addDays(3)->setTime(18, 0), 'match_date' => $day->copy()->addDays(3)->toDateString()]);

        $arrival = $this->sequence($event, 'CT-ARR', 'Arrival sequence', 'arrival', [['CP-MEET', 'Meet at airport', 0], ['CP-HOTEL', 'Arrive at hotel', 60]]);
        $match = $this->sequence($event, 'CT-MD', 'Match day sequence', 'match', [['CP-DEP', 'Depart hotel', -120], ['CP-STD', 'Arrive at stadium', -75]]);

        $arrivalTemplate = MovementTemplate::create(['event_id' => $event->id, 'code' => 'MVT-ARR', 'name' => 'Team Arrival', 'scenario_type' => 'arrival_day', 'functional_area' => 'LOG', 'total_legs' => 1, 'is_active' => true]);
        $arrivalTemplate->legs()->create(['order' => 1, 'name' => 'Airport to hotel', 'leg_type' => 'arrival', 'from_location' => 'Airport', 'to_location' => 'Team Hotel', 'checkpoint_template_id' => $arrival->id, 'estimated_duration_minutes' => 60]);

        $matchTemplate = MovementTemplate::create(['event_id' => $event->id, 'code' => 'MVT-MD', 'name' => 'Team Match Day', 'scenario_type' => 'match_day', 'functional_area' => 'LOG', 'total_legs' => 1, 'is_active' => true]);
        $matchTemplate->legs()->create(['order' => 1, 'name' => 'Hotel to stadium', 'leg_type' => 'match', 'from_location' => 'Team Hotel', 'to_location' => 'Stadium', 'checkpoint_template_id' => $match->id, 'estimated_duration_minutes' => 45]);

        foreach (['V-01' => 'Coaster', 'V-02' => 'Bus'] as $code => $type) {
            Vehicle::create(['code' => $code, 'provider_id' => $provider->id, 'plate_number' => "P{$code}", 'vehicle_type' => $type, 'capacity' => 30, 'status' => 'available', 'is_active' => true])
                ->events()->attach($event);
        }
        foreach (['Driver One', 'Driver Two'] as $name) {
            Driver::create(['provider_id' => $provider->id, 'name' => $name, 'phone' => '+97450000000', 'status' => 'available'])
                ->events()->attach($event);
        }

        // Another provider's vehicle in the pool: never offered for this event's (GWC) movements.
        $other = FleetProvider::withoutGlobalScopes()->create(['code' => 'OTH', 'name' => 'Other Co']);
        Vehicle::create(['code' => 'V-OTHER', 'provider_id' => $other->id, 'plate_number' => 'POTHER', 'vehicle_type' => 'Van', 'capacity' => 8, 'status' => 'available', 'is_active' => true])
            ->events()->attach($event);

        $this->sharedEvent($provider, $admin);
    }

    /**
     * A second event run by the same provider, sharing Driver One, with one movement tomorrow
     * 10:15-11:15 on Driver One: it overlaps the arrival the planning tests crew, so Crew
     * Assignment shows the clash once both events are ticked.
     */
    private function sharedEvent(FleetProvider $provider, User $admin): void
    {
        $event = Event::create([
            'name' => 'E2E Shield', 'short_name' => 'SHD', 'active_flag' => false,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDays(30)->toDateString(),
            'fleet_provider_id' => $provider->id,
        ]);
        $admin->events()->attach($event);

        $driver = Driver::where('name', 'Driver One')->first();
        $driver->events()->attach($event);

        Country::firstOrCreate(['country_code' => 'OMA'], ['country_name' => 'Oman']);
        $team = Team::create(['event_id' => $event->id, 'code' => 'OMA-17', 'team_name' => 'Oman', 'country_id' => 'OMA', 'is_active' => true]);
        $tomorrow = now()->addDay()->startOfDay();
        $plan = Plan::create(['event_id' => $event->id, 'code' => 'PLN-SHD-001', 'name' => 'Shield day', 'date' => $tomorrow->toDateString(), 'status' => 'draft']);

        Movement::create([
            'code' => 'TRP-90001', 'event_id' => $event->id, 'plan_id' => $plan->id, 'team_id' => $team->id,
            'kind' => 'transfer', 'functional_area' => 'LOG', 'from_location' => 'Team Hotel', 'to_location' => 'Training site',
            'window_start' => $tomorrow->copy()->setTime(10, 15), 'window_end' => $tomorrow->copy()->setTime(11, 15),
            'driver_id' => $driver->id, 'fleet_provider_id' => $provider->id, 'status' => 'scheduled', 'source' => 'manual',
        ]);
    }

    /** @param array<int, array{0: string, 1: string, 2: int}> $steps code, name, offset from the reference time */
    private function sequence(Event $event, string $code, string $name, string $type, array $steps): CheckpointTemplate
    {
        $template = CheckpointTemplate::create(['event_id' => $event->id, 'code' => $code, 'name' => $name, 'movement_type' => $type, 'is_active' => true]);

        foreach ($steps as $order => [$stepCode, $stepName, $offset]) {
            $checkpoint = Checkpoint::create(['code' => $stepCode, 'name' => $stepName, 'type' => 'manual', 'is_active' => true]);
            $template->checkpoints()->attach($checkpoint->id, ['order' => $order + 1, 'is_required' => true, 'estimated_minutes' => 15]);
            app(SettingsService::class)->setSetting("movement_offset.{$type}", $offset, Setting::SCOPE_GLOBAL, null, null, $checkpoint->id);
        }

        return $template;
    }
}
