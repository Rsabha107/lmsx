<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Event;
use App\Models\GameMatch;
use App\Models\RosterMember;
use App\Models\Team;
use App\Models\TeamStay;
use App\Models\User;
use App\Services\HotelScheduleService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class HotelScheduleTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    /** @var array<string, Team> */
    private array $teams = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $this->event = $this->createEvent();
        // Same hotel typed two ways, and a second hotel.
        foreach (['UGA' => 'Best Plaza West Bay Hotel ', 'QAT' => ' BEST PLAZA  west bay hotel', 'MOZ' => 'Best Plaza West Bay Hotel', 'URU' => 'Crown Plaza Doha'] as $code => $hotel) {
            // firstOrCreate: a random fixture country code could clash with a real one.
            Country::firstOrCreate(['country_code' => $code], ['country_name' => $code]);
            $team = Team::create(['event_id' => $this->event->id, 'code' => "{$code}-17", 'team_name' => $code, 'country_id' => $code]);
            TeamStay::create(['event_id' => $this->event->id, 'team_id' => $team->id, 'hotel_name' => $hotel]);
            $this->teams[$code] = $team;
        }

        $this->match('M1', 'UGA', 'URU', '2026-11-19 16:30');
        $this->match('M2', 'QAT', 'MOZ', '2026-11-19 18:30', 'MD-R1');
        $this->match('M3', 'UGA', 'QAT', '2026-11-21 16:00', 'MD-R2');
    }

    private function match(string $number, string $home, string $away, string $kickOff, string $stage = 'MD-R1'): GameMatch
    {
        return GameMatch::create([
            'event_id' => $this->event->id, 'match_number' => $number, 'stage' => $stage,
            'team1_id' => $this->teams[$home]->id, 'team2_id' => $this->teams[$away]->id, 'kick_off' => $kickOff,
        ]);
    }

    private function as(string $role): static
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->events()->attach($this->event->id);

        return $this->actingAs($user)->withSession(['active_event_id' => $this->event->id]);
    }

    private function supervisor(string $name = 'Spvr One'): User
    {
        $user = User::factory()->create(['name' => $name]);
        RosterMember::create(['event_id' => $this->event->id, 'user_id' => $user->id, 'sort' => 1]);

        return $user;
    }

    private function hotelDay(array $schedule, string $hotel, string $date): ?array
    {
        return collect($schedule['hotels'])->firstWhere('key', $hotel)['days'][$date] ?? null;
    }

    public function test_the_tab_lists_each_hotels_teams_with_report_and_shift_end_times(): void
    {
        $this->as('admin')->get('/duty-roster?tab=hotels')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('DutyRoster')
                ->has('hotelSchedule.hotels', 2)
                ->where('hotelSchedule.hotels.0.key', 'BEST PLAZA WEST BAY HOTEL')
                ->where('hotelSchedule.hotels.0.teams', ['MOZ', 'QAT', 'UGA'])
                ->where('hotelSchedule.days.0', fn ($d) => $d['date'] === '2026-11-19' && $d['stage'] === 'GS' && ! $d['rest'])
                ->where('hotelSchedule.days.1', fn ($d) => $d['date'] === '2026-11-20' && $d['rest'])
                // Ordered by report time: KO minus 4h30, shift ends KO plus 4h30.
                ->where('hotelSchedule.hotels.0.days.2026-11-19.teams', fn ($teams) => collect($teams)->map(fn ($t) => "{$t['code']} {$t['report']}-{$t['end']}")->all()
                    === ['UGA 12:00-21:00', 'MOZ 14:00-23:00', 'QAT 14:00-23:00']));

        // Other tabs don't build it.
        $this->as('admin')->get('/duty-roster')->assertInertia(fn (Assert $page) => $page->missing('hotelSchedule'));
    }

    public function test_supervisor_crew_teams_kit_runs_and_vsa_are_saved(): void
    {
        $spvr = $this->supervisor();
        $as = $this->as('admin');
        $match = GameMatch::where('match_number', 'M1')->first();

        $as->put('/duty-roster/hotels/day', ['hotel' => 'BEST PLAZA WEST BAY HOTEL', 'date' => '2026-11-19', 'supervisor_id' => $spvr->id])->assertSessionHasNoErrors();
        $as->put('/duty-roster/hotels/day', [
            'hotel' => 'BEST PLAZA WEST BAY HOTEL', 'date' => '2026-11-19',
            'departure' => ['crews' => [2, 1, 1], 'time' => '12:00'], 'arrival' => ['crews' => [3], 'time' => '14:00'],
        ])->assertSessionHasNoErrors();
        $as->put('/duty-roster/hotels/crew', ['match_id' => $match->id, 'team_id' => $this->teams['UGA']->id, 'crew_team' => 1])->assertSessionHasNoErrors();
        $as->put('/duty-roster/hotels/vsa', ['date' => '2026-11-19', 'crews' => [12, 13]])->assertSessionHasNoErrors();

        $schedule = app(HotelScheduleService::class)->build($this->event->id);
        $day = $this->hotelDay($schedule, 'BEST PLAZA WEST BAY HOTEL', '2026-11-19');
        $this->assertSame($spvr->id, $day['supervisor_id']);
        $this->assertSame(['crews' => [1, 2], 'time' => '12:00'], $day['departure']);
        $this->assertSame(['crews' => [3], 'time' => '14:00'], $day['arrival']);
        $this->assertSame(1, collect($day['teams'])->firstWhere('code', 'UGA')['crew']);
        $this->assertSame([12, 13], $schedule['vsa']->{'2026-11-19'});
        $this->assertDatabaseHas('audit_logs', ['action' => 'Hotel schedule updated']);

        // Saving the supervisor alone keeps the kit runs.
        $as->put('/duty-roster/hotels/day', ['hotel' => 'BEST PLAZA WEST BAY HOTEL', 'date' => '2026-11-19', 'supervisor_id' => null]);
        $day = $this->hotelDay(app(HotelScheduleService::class)->build($this->event->id), 'BEST PLAZA WEST BAY HOTEL', '2026-11-19');
        $this->assertNull($day['supervisor_id']);
        $this->assertSame([1, 2], $day['departure']['crews']);
    }

    public function test_bad_input_is_refused(): void
    {
        $outsider = User::factory()->create();
        $as = $this->as('admin');
        $match = GameMatch::where('match_number', 'M1')->first();

        $as->put('/duty-roster/hotels/day', ['hotel' => 'BEST PLAZA WEST BAY HOTEL', 'date' => '2026-11-19', 'supervisor_id' => $outsider->id])
            ->assertSessionHasErrors('supervisor_id');
        $as->put('/duty-roster/hotels/day', ['hotel' => 'NOWHERE INN', 'date' => '2026-11-19'])->assertSessionHasErrors('hotel');
        $as->put('/duty-roster/hotels/day', ['hotel' => 'BEST PLAZA WEST BAY HOTEL', 'date' => '2026-11-19', 'departure' => ['crews' => [1], 'time' => '25:00']])
            ->assertSessionHasErrors('departure.time');
        $as->put('/duty-roster/hotels/crew', ['match_id' => $match->id, 'team_id' => $this->teams['QAT']->id, 'crew_team' => 1])->assertStatus(422);
    }

    public function test_changing_the_offsets_moves_the_report_times(): void
    {
        $this->as('admin')->put('/duty-roster/hotels/offsets', ['report_before' => 240, 'end_after' => 300])->assertSessionHasNoErrors();

        $day = $this->hotelDay(app(HotelScheduleService::class)->build($this->event->id), 'BEST PLAZA WEST BAY HOTEL', '2026-11-19');
        $uga = collect($day['teams'])->firstWhere('code', 'UGA');
        $this->assertSame(['12:30', '21:30'], [$uga['report'], $uga['end']]);
    }

    public function test_auto_fill_numbers_crews_by_report_time_and_suggests_kit_runs(): void
    {
        $match = GameMatch::where('match_number', 'M2')->first();
        $this->as('admin')->put('/duty-roster/hotels/crew', ['match_id' => $match->id, 'team_id' => $this->teams['MOZ']->id, 'crew_team' => 1]);

        $this->as('admin')->post('/duty-roster/hotels/auto-fill')->assertSessionHas('success');

        $day = $this->hotelDay(app(HotelScheduleService::class)->build($this->event->id), 'BEST PLAZA WEST BAY HOTEL', '2026-11-19');
        // MOZ keeps crew 1, so UGA and QAT take the next free numbers in report order (ties by code).
        $this->assertSame(['UGA' => 2, 'MOZ' => 1, 'QAT' => 3], collect($day['teams'])->mapWithKeys(fn ($t) => [$t['code'] => $t['crew']])->all());
        $this->assertSame(['crews' => [2], 'time' => '12:00'], $day['departure']);
        $this->assertSame(['crews' => [3], 'time' => '14:00'], $day['arrival']);

        $this->as('admin')->post('/duty-roster/hotels/auto-fill')->assertSessionHas('success', 'Nothing left to fill.');
    }

    public function test_only_roster_managers_reach_it(): void
    {
        foreach (['agency', 'transport'] as $role) {
            $this->as($role)->put('/duty-roster/hotels/vsa', ['date' => '2026-11-19', 'crews' => [1]])->assertForbidden();
            $this->as($role)->get('/duty-roster/hotels/export')->assertForbidden();
        }
    }

    public function test_the_excel_export_lays_out_hotels_by_day(): void
    {
        $spvr = $this->supervisor('Aida');
        $this->as('admin')->put('/duty-roster/hotels/day', ['hotel' => 'BEST PLAZA WEST BAY HOTEL', 'date' => '2026-11-19', 'supervisor_id' => $spvr->id]);
        $this->as('admin')->post('/duty-roster/hotels/auto-fill');

        $response = $this->as('admin')->get('/duty-roster/hotels/export')->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'hts');
        file_put_contents($path, $response->streamedContent());
        $sheet = IOFactory::load($path)->getActiveSheet();
        unlink($path);

        $this->assertSame('1. Best Plaza West Bay Hotel', $sheet->getCell('A6')->getValue());
        $this->assertSame("3 PMAs (3 crew)\nSpvr: Aida\nUGA 12:00 · Crew 1\nMOZ 14:00 · Crew 2\nQAT 14:00 · Crew 3", $sheet->getCell('B6')->getValue());
        $this->assertSame('Crew 1 @ 12:00', $sheet->getCell('B7')->getValue());
        $this->assertSame('REST DAY', $sheet->getCell('C6')->getValue());
    }
}
