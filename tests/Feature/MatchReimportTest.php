<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Venue;
use App\Services\MatchImportService;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class MatchReimportTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_a_moved_kick_off_reschedules_planned_movements_and_reports_issued_jobs(): void
    {
        $event = $this->createEvent();
        $home = $this->createTeam($event);
        $away = $this->createTeam($event);
        $plan = $this->createPlan($event);

        $match = GameMatch::create([
            'event_id' => $event->id,
            'match_number' => 'M01',
            'team1_id' => $home->id,
            'team2_id' => $away->id,
            'match_date' => '2026-11-19',
            'kick_off' => '2026-11-19 18:00:00',
        ]);

        $planned = $this->createMovement($event, $plan, $home, [
            'kind' => 'match',
            'match_id' => $match->id,
            'window_start' => '2026-11-19 15:00:00',
            'window_end' => '2026-11-19 16:00:00',
        ]);

        $dispatched = $this->createMovement($event, $plan, $away, [
            'kind' => 'match',
            'match_id' => $match->id,
            'window_start' => '2026-11-19 15:00:00',
            'window_end' => '2026-11-19 16:00:00',
            'job_id' => 'JOB-TEST-0002',
        ]);

        $result = app(MatchImportService::class)->import([[
            'match_number' => 'M01',
            'match_date' => '2026-11-19',
            'kick_off' => '20:30',
        ]], $event->id);

        $this->assertSame(1, $result['updated']);

        $offset = app(SettingsService::class)->getMovementOffset('match', $event->id);
        $planned->refresh();
        $this->assertTrue(Carbon::parse('2026-11-19 20:30')->addMinutes($offset)->equalTo($planned->window_start));
        $this->assertEquals(60, $planned->window_start->diffInMinutes($planned->window_end));

        $this->assertSame('2026-11-19 15:00', $dispatched->refresh()->window_start->format('Y-m-d H:i'));

        $statuses = collect($result['match_changes'][0]['movements'])->pluck('status', 'id');
        $this->assertSame('updated', $statuses[$planned->id]);
        $this->assertSame('needs_review', $statuses[$dispatched->id]);
    }

    public function test_a_new_date_without_a_kick_off_moves_the_kick_off_with_it(): void
    {
        $event = $this->createEvent();

        $match = GameMatch::create([
            'event_id' => $event->id,
            'match_number' => 'M02',
            'match_date' => '2026-11-19',
            'kick_off' => '2026-11-19 18:00:00',
        ]);

        app(MatchImportService::class)->import([[
            'match_number' => 'M02',
            'match_date' => '2026-11-20',
        ]], $event->id);

        $match->refresh();
        $this->assertSame('2026-11-20', $match->match_date->format('Y-m-d'));
        $this->assertSame('2026-11-20 18:00', $match->kick_off->format('Y-m-d H:i'));
    }

    public function test_a_team_dropped_from_the_match_is_flagged_not_moved(): void
    {
        $event = $this->createEvent();
        $home = $this->createTeam($event);
        $away = $this->createTeam($event);
        $replacement = $this->createTeam($event);
        $plan = $this->createPlan($event);

        $match = GameMatch::create([
            'event_id' => $event->id,
            'match_number' => 'M03',
            'team1_id' => $home->id,
            'team2_id' => $away->id,
            'match_date' => '2026-11-19',
            'kick_off' => '2026-11-19 18:00:00',
        ]);

        $orphaned = $this->createMovement($event, $plan, $away, [
            'kind' => 'match',
            'match_id' => $match->id,
        ]);

        $result = app(MatchImportService::class)->import([[
            'match_number' => 'M03',
            'team2_code' => $replacement->code,
        ]], $event->id);

        $entry = $result['match_changes'][0]['movements'][0];
        $this->assertSame($orphaned->id, $entry['id']);
        $this->assertSame('needs_review', $entry['status']);
        $this->assertSame($away->id, $orphaned->refresh()->team_id);
    }

    public function test_a_venue_change_flags_issued_jobs_and_stale_route_text(): void
    {
        $event = $this->createEvent();
        $home = $this->createTeam($event);
        $away = $this->createTeam($event);
        $plan = $this->createPlan($event);
        $bayt = Venue::create(['name' => 'Al Bayt Stadium']);
        Venue::create(['name' => 'Al Janoub Stadium']);

        $match = GameMatch::create([
            'event_id' => $event->id,
            'match_number' => 'M04',
            'team1_id' => $home->id,
            'team2_id' => $away->id,
            'venue_id' => $bayt->id,
            'match_date' => '2026-11-19',
            'kick_off' => '2026-11-19 18:00:00',
        ]);

        $namedRoute = $this->createMovement($event, $plan, $home, [
            'kind' => 'match',
            'match_id' => $match->id,
            'to_location' => 'Al Bayt Stadium',
        ]);
        $genericRoute = $this->createMovement($event, $plan, $home, [
            'kind' => 'match',
            'match_id' => $match->id,
        ]);
        $dispatched = $this->createMovement($event, $plan, $away, [
            'kind' => 'match',
            'match_id' => $match->id,
            'job_id' => 'JOB-TEST-0003',
        ]);

        $result = app(MatchImportService::class)->import([[
            'match_number' => 'M04',
            'venue' => 'al janoub stadium',
        ]], $event->id);

        $change = $result['match_changes'][0];
        $this->assertSame('Al Bayt Stadium', $change['venue_before']);
        $this->assertSame('Al Janoub Stadium', $change['venue_after']);

        $statuses = collect($change['movements'])->pluck('status', 'id');
        $this->assertSame('needs_review', $statuses[$namedRoute->id]);
        $this->assertSame('needs_review', $statuses[$dispatched->id]);
        $this->assertArrayNotHasKey($genericRoute->id, $statuses->all());
    }
}
