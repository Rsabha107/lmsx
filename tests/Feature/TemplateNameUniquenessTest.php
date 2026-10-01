<?php

namespace Tests\Feature;

use App\Models\CheckpointTemplate;
use App\Models\Event;
use App\Models\MovementTemplate;
use App\Services\TemplateCopyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class TemplateNameUniquenessTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = $this->createEvent();
    }

    private function admin(?Event $event = null): static
    {
        $event ??= $this->event;
        $user = $this->createUserWithRole('admin');
        $user->events()->attach($event);

        return $this->actingAs($user)->withSession(['active_event_id' => $event->id]);
    }

    private function checkpointTemplate(Event $event, string $code, string $name): CheckpointTemplate
    {
        return CheckpointTemplate::create([
            'event_id' => $event->id, 'code' => $code, 'name' => $name, 'movement_type' => 'arrival', 'is_active' => true,
        ]);
    }

    private function movementTemplate(Event $event, string $code, string $name): MovementTemplate
    {
        return MovementTemplate::create([
            'event_id' => $event->id, 'code' => $code, 'name' => $name, 'scenario_type' => 'match_day', 'is_active' => true,
        ]);
    }

    public function test_checkpoint_template_name_must_be_unique_within_the_event(): void
    {
        $this->checkpointTemplate($this->event, 'CT-1', 'Airport Arrival');

        $this->admin()->post('/admin/checkpoint-templates', [
            'code' => 'CT-2', 'name' => 'Airport Arrival', 'movement_type' => 'arrival',
        ])->assertSessionHasErrors('name');

        $other = $this->createEvent();
        $this->admin($other)->post('/admin/checkpoint-templates', [
            'code' => 'CT-3', 'name' => 'Airport Arrival', 'movement_type' => 'arrival',
        ])->assertSessionHasNoErrors();
    }

    public function test_checkpoint_template_can_keep_its_own_name_but_not_take_another(): void
    {
        $this->checkpointTemplate($this->event, 'CT-1', 'Airport Arrival');
        $second = $this->checkpointTemplate($this->event, 'CT-2', 'Stadium Transfer');

        $this->admin()->put("/admin/checkpoint-templates/{$second->id}", [
            'code' => 'CT-2', 'name' => 'Stadium Transfer', 'movement_type' => 'arrival',
        ])->assertSessionHasNoErrors();

        $this->admin()->put("/admin/checkpoint-templates/{$second->id}", [
            'code' => 'CT-2', 'name' => 'Airport Arrival', 'movement_type' => 'arrival',
        ])->assertSessionHasErrors('name');
    }

    public function test_movement_template_name_must_be_unique_within_the_event(): void
    {
        $existing = $this->movementTemplate($this->event, 'MT-1', 'Team Match Day');
        $second = $this->movementTemplate($this->event, 'MT-2', 'Training Day');

        $this->admin()->post('/admin/movement-templates', [
            'code' => 'MT-3', 'name' => 'Team Match Day', 'scenario_type' => 'match_day',
        ])->assertSessionHasErrors('name');

        $this->admin()->put("/admin/movement-templates/{$second->id}", [
            'code' => 'MT-2', 'name' => $existing->name, 'scenario_type' => 'match_day',
        ])->assertSessionHasErrors('name');

        $this->admin()->put("/admin/movement-templates/{$existing->id}", [
            'code' => 'MT-1', 'name' => 'Team Match Day', 'scenario_type' => 'match_day',
        ])->assertSessionHasNoErrors();
    }

    public function test_codes_are_stored_in_capitals(): void
    {
        $this->admin()->post('/admin/checkpoint-templates', [
            'code' => ' tpl-arr ', 'name' => 'Airport Arrival', 'movement_type' => 'arrival',
        ])->assertSessionHasNoErrors();
        $this->admin()->post('/admin/movement-templates', [
            'code' => 'mvt-md', 'name' => 'Team Match Day', 'scenario_type' => 'match_day',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('checkpoint_templates', ['code' => 'TPL-ARR']);
        $this->assertSame('MVT-MD', MovementTemplate::where('name', 'Team Match Day')->value('code'));
        $this->assertSame('CKP-X', \App\Models\Checkpoint::make(['code' => 'ckp-x'])->code);
    }

    public function test_editing_a_movement_template_can_add_legs(): void
    {
        $template = $this->movementTemplate($this->event, 'MT-1', 'Team Match Day');
        $sequence = $this->checkpointTemplate($this->event, 'CT-1', 'Stadium Transfer');
        $leg = fn (int $order) => [
            'order' => $order, 'checkpoint_template_id' => $sequence->id, 'leg_type' => 'transfer',
            'from_location' => 'Hotel', 'to_location' => 'Stadium', 'transport_type' => 'bus', 'estimated_duration_minutes' => 30,
        ];

        $this->admin()->put("/admin/movement-templates/{$template->id}", [
            'code' => 'MT-1', 'name' => 'Team Match Day', 'scenario_type' => 'match_day', 'legs' => [$leg(1), $leg(2)],
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $template->legs()->count());
        $this->assertSame(2, $template->refresh()->total_legs);
    }

    public function test_copying_into_an_event_with_the_same_name_gets_a_suffix(): void
    {
        $target = $this->createEvent();
        $this->checkpointTemplate($target, 'CT-T', 'Airport Arrival');
        $source = $this->checkpointTemplate($this->event, 'CT-S', 'Airport Arrival');

        $copy = app(TemplateCopyService::class)->copyCheckpointTemplate($source->load('checkpoints'), $target->id);

        $this->assertSame('Airport Arrival (2)', $copy->name);
    }
}
