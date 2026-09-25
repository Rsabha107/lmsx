<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\CheckpointTemplate;
use App\Models\MovementTemplate;
use App\Models\Setting;
use App\Services\JobGenerationService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class MatchStartOffsetTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_the_first_checkpoints_event_setting_wins_over_the_global_one_and_the_match_default(): void
    {
        $event = $this->createEvent();
        [$template, $checkpoint] = $this->matchDayTemplate($event->id);
        $settings = app(SettingsService::class);

        $settings->setSetting('movement_offset.match', '-300', Setting::SCOPE_GLOBAL);
        $this->assertSame(
            ['minutes' => -300, 'source' => 'the global match default'],
            $this->offsetFor($template, $event->id),
        );

        $settings->setSetting('movement_offset.match', '-240', Setting::SCOPE_GLOBAL, null, null, $checkpoint->id);
        $this->assertSame(
            ['minutes' => -240, 'source' => 'the global setting for the "Depart Hotel" checkpoint'],
            $this->offsetFor($template, $event->id),
        );

        $settings->setSetting('movement_offset.match', '-210', Setting::SCOPE_EVENT, $event->id, null, $checkpoint->id);
        $this->assertSame(
            ['minutes' => -210, 'source' => 'the event setting for the "Depart Hotel" checkpoint'],
            $this->offsetFor($template, $event->id),
        );
    }

    public function test_the_plans_page_ships_the_offset_with_match_day_templates(): void
    {
        $event = $this->createEvent();
        $this->matchDayTemplate($event->id);
        app(SettingsService::class)->setSetting('movement_offset.match', '-180', Setting::SCOPE_EVENT, $event->id);

        $this->actingAs($this->createUserWithRole('admin'))
            ->withSession(['active_event_id' => $event->id])
            ->get('/plans')
            ->assertInertia(fn ($page) => $page
                ->where('movementTemplates.0.match_start_offset.minutes', -180)
                ->where('movementTemplates.0.match_start_offset.source', "the event's match default"));
    }

    /** @return array{0: MovementTemplate, 1: Checkpoint} */
    private function matchDayTemplate(int $eventId): array
    {
        $checkpoint = Checkpoint::create(['code' => 'CP-DEP', 'name' => 'Depart Hotel', 'is_active' => true]);
        $sequence = CheckpointTemplate::create([
            'event_id' => $eventId, 'code' => 'CT-MD', 'name' => 'Match day sequence', 'movement_type' => 'match', 'is_active' => true,
        ]);
        $sequence->checkpoints()->attach($checkpoint->id, ['order' => 1]);

        $template = MovementTemplate::create([
            'event_id' => $eventId, 'code' => 'MVT-MD', 'name' => 'Team Match Day',
            'scenario_type' => 'match_day', 'functional_area' => 'LOG', 'is_active' => true,
        ]);
        $template->legs()->create([
            'order' => 1, 'name' => 'Hotel to stadium', 'leg_type' => 'match', 'checkpoint_template_id' => $sequence->id,
        ]);

        return [$template, $checkpoint];
    }

    private function offsetFor(MovementTemplate $template, int $eventId): ?array
    {
        return app(JobGenerationService::class)->matchStartOffset(
            $template->fresh()->load('legs.checkpointTemplate.checkpoints'),
            $eventId,
        );
    }
}
