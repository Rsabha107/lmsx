<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Event;
use App\Models\GameMatch;
use App\Models\Movement;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Services\ConflictDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class CrewConflictTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = $this->createEvent();
        $this->plan = $this->createPlan($this->event);
    }

    /**
     * A match-day job: 30-min pickup window, then checkpoint times as given
     * (e.g. out to the venue, final whistle, return).
     *
     * @param  array<int, string>  $checkpoints  'Y-m-d H:i'
     * @param  array<string, mixed>  $crew
     */
    private function matchJob(string $venue, string $start, array $checkpoints, array $crew): Movement
    {
        $team = $this->createTeam($this->event);
        $match = GameMatch::create([
            'event_id' => $this->event->id, 'match_number' => 'M'.$team->id, 'team1_id' => $team->id,
            'kick_off' => date('Y-m-d H:i', strtotime($start.' +4 hours')),
            'venue_id' => Venue::firstOrCreate(['name' => $venue])->id,
        ]);

        $movement = $this->createMovement($this->event, $this->plan, $team, $crew + [
            'kind' => 'match', 'match_id' => $match->id,
            'window_start' => $start, 'window_end' => date('Y-m-d H:i', strtotime($start.' +30 minutes')),
        ]);
        $job = $this->createJob($this->event, $movement, $team);
        foreach ($checkpoints as $i => $at) {
            $this->createCheckpoint($this->event, $job, ['order' => $i + 1, 'scheduled_at' => $at]);
        }

        return $movement;
    }

    private function conflicts(): array
    {
        return app(ConflictDetectionService::class)->forEvent($this->event->id);
    }

    private function ofType(string $type): array
    {
        return array_values(array_filter($this->conflicts(), fn ($c) => $c['type'] === $type));
    }

    /** Ireland out at 12:30, back 20:30–21:00; Mexico out at 14:30, back 22:30–23:00. */
    private function irelandAndMexico(string $irelandVenue, string $mexicoVenue, array $crew): void
    {
        $this->matchJob($irelandVenue, '2026-11-27 12:30',
            ['2026-11-27 12:30', '2026-11-27 12:55', '2026-11-27 13:30', '2026-11-27 19:00', '2026-11-27 20:30', '2026-11-27 21:00'], $crew);
        $this->matchJob($mexicoVenue, '2026-11-27 14:30',
            ['2026-11-27 14:30', '2026-11-27 14:55', '2026-11-27 15:30', '2026-11-27 21:00', '2026-11-27 22:30', '2026-11-27 23:00'], $crew);
    }

    public function test_one_supervisor_across_two_pitches_of_the_same_complex_is_allowed(): void
    {
        $this->irelandAndMexico('ASPIRE ZONE (PITCH 1)', 'ASPIRE ZONE (PITCH 7)', ['field_supervisor_id' => User::factory()->create()->id]);

        $this->assertSame([], $this->ofType('Supervisor Double-Booked'));
    }

    public function test_one_supervisor_across_two_venues_is_double_booked(): void
    {
        $this->irelandAndMexico('ASPIRE ZONE (PITCH 1)', 'KHALIFA STADIUM', ['field_supervisor_id' => User::factory()->create()->id]);

        $clashes = $this->ofType('Supervisor Double-Booked');
        $this->assertCount(1, $clashes);
        $this->assertSame('high', $clashes[0]['sev']);
    }

    public function test_a_driver_covering_a_job_while_the_other_team_waits_is_split_duty_not_a_clash(): void
    {
        $this->irelandAndMexico('ASPIRE ZONE (PITCH 1)', 'KHALIFA STADIUM', ['driver_id' => Driver::create(['name' => 'Mia', 'status' => 'available'])->id]);

        $this->assertSame([], $this->ofType('Driver Double-Booked'));
        $split = $this->ofType('Split Duty');
        $this->assertCount(1, $split);
        $this->assertSame('low', $split[0]['sev']);
    }

    public function test_a_driver_working_two_jobs_at_once_is_double_booked(): void
    {
        $driver = Driver::create(['name' => 'Mia', 'status' => 'available'])->id;
        $this->matchJob('A', '2026-11-27 12:30', ['2026-11-27 12:30', '2026-11-27 13:00', '2026-11-27 13:30'], ['driver_id' => $driver]);
        $this->matchJob('B', '2026-11-27 13:00', ['2026-11-27 13:00', '2026-11-27 13:30', '2026-11-27 14:00'], ['driver_id' => $driver]);

        $clashes = $this->ofType('Driver Double-Booked');
        $this->assertCount(1, $clashes);
        $this->assertStringContainsString('30 minutes overlap', $clashes[0]['text']);
    }

    public function test_a_driver_needs_eleven_hours_between_days(): void
    {
        $driver = Driver::create(['name' => 'Mia', 'status' => 'available'])->id;
        $this->matchJob('A', '2026-11-27 18:00', ['2026-11-27 18:00', '2026-11-27 23:00'], ['driver_id' => $driver]);
        $this->matchJob('B', '2026-11-28 07:00', ['2026-11-28 07:00', '2026-11-28 08:00'], ['driver_id' => $driver]);

        $rest = $this->ofType('Insufficient Rest');
        $this->assertCount(1, $rest);
        $this->assertStringContainsString('8.0 hours', $rest[0]['text']);
    }

    public function test_enough_rest_raises_nothing(): void
    {
        $driver = Driver::create(['name' => 'Mia', 'status' => 'available'])->id;
        $this->matchJob('A', '2026-11-27 18:00', ['2026-11-27 18:00', '2026-11-27 20:00'], ['driver_id' => $driver]);
        $this->matchJob('B', '2026-11-28 08:00', ['2026-11-28 08:00', '2026-11-28 09:00'], ['driver_id' => $driver]);

        $this->assertSame([], $this->ofType('Insufficient Rest'));
    }
}
