<?php

namespace Tests\Feature;

use App\Models\CheckpointTemplate;
use App\Models\GameMatch;
use App\Models\MovementTemplate;
use App\Services\JobGenerationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class TemplateLegDurationTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private function matchTemplate(int $eventId, ?int $minutes): MovementTemplate
    {
        $sequence = CheckpointTemplate::create([
            'event_id' => $eventId, 'code' => 'CT-MD', 'name' => 'Match day sequence', 'movement_type' => 'match', 'is_active' => true,
        ]);

        $template = MovementTemplate::create([
            'event_id' => $eventId, 'code' => 'MVT-MD', 'name' => 'Team Match Day',
            'scenario_type' => 'match_day', 'is_active' => true,
        ]);
        $template->legs()->create([
            'order' => 1, 'leg_type' => 'match', 'checkpoint_template_id' => $sequence->id,
            'estimated_duration_minutes' => $minutes,
        ]);

        return $template;
    }

    public function test_changing_a_leg_duration_re_ends_ungenerated_movements_and_keeps_their_start(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $template = $this->matchTemplate($event->id, 120);
        $plan = $this->createPlan($event);
        $plan->update(['movement_template_id' => $template->id]);

        $start = Carbon::parse('2026-11-27 12:30');
        $open = $this->createMovement($event, $plan, $team, ['kind' => 'match', 'window_start' => $start, 'window_end' => $start->copy()->addMinutes(30)]);
        $generated = $this->createMovement($event, $plan, $team, ['kind' => 'match', 'window_start' => $start, 'window_end' => $start->copy()->addMinutes(30)]);
        $generated->update(['job_id' => $this->createJob($event, $generated, $team)->job_id]);

        $this->assertSame(1, app(JobGenerationService::class)->applyTemplateDurations($template));

        $open->refresh();
        $this->assertTrue($open->window_start->equalTo($start));
        $this->assertSame('14:30', $open->window_end->format('H:i'));
        $this->assertSame('13:00', $generated->refresh()->window_end->format('H:i'));
    }

    public function test_recomputing_a_window_takes_its_length_from_the_template_leg(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $template = $this->matchTemplate($event->id, 120);
        $plan = $this->createPlan($event);
        $plan->update(['movement_template_id' => $template->id]);

        $match = GameMatch::create(['event_id' => $event->id, 'match_number' => 'M1', 'team1_id' => $team->id, 'kick_off' => '2026-11-27 17:00']);
        $movement = $this->createMovement($event, $plan, $team, [
            'kind' => 'match', 'match_id' => $match->id,
            'window_start' => '2026-11-27 12:00', 'window_end' => '2026-11-27 12:30',
        ]);

        $this->assertTrue(app(JobGenerationService::class)->recomputeMovementWindow($movement));

        $movement->refresh();
        $this->assertSame('17:00', $movement->window_start->format('H:i'));
        $this->assertSame('19:00', $movement->window_end->format('H:i'));
    }

    public function test_a_leg_without_a_duration_keeps_the_existing_length(): void
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $template = $this->matchTemplate($event->id, null);
        $plan = $this->createPlan($event);
        $plan->update(['movement_template_id' => $template->id]);

        $movement = $this->createMovement($event, $plan, $team, [
            'kind' => 'match', 'window_start' => '2026-11-27 12:00', 'window_end' => '2026-11-27 12:45',
        ]);

        $this->assertSame(0, app(JobGenerationService::class)->applyTemplateDurations($template));
        $this->assertSame('12:45', $movement->refresh()->window_end->format('H:i'));
    }
}
