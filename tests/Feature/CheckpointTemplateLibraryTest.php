<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\CheckpointTemplate;
use App\Models\Event;
use App\Models\MovementTemplate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Library → Checkpoint Templates: typical route, Duplicate and "used in". */
class CheckpointTemplateLibraryTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->event = $this->createEvent();
        $this->admin = $this->createUserWithRole('admin');
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin)->withSession(['active_event_id' => $this->event->id]);
    }

    private function checkpoint(string $code): Checkpoint
    {
        return Checkpoint::create(['code' => $code, 'name' => "Step {$code}", 'type' => 'dispatch', 'category' => 'Logistics', 'capture_method' => 'manual', 'is_active' => true]);
    }

    private function template(Event $event, string $code = 'TMPL-ARR'): CheckpointTemplate
    {
        $template = CheckpointTemplate::create(['event_id' => $event->id, 'code' => $code, 'name' => 'Arrival steps', 'movement_type' => 'arrival', 'from_location' => 'Airport', 'to_location' => 'Team Hotel', 'is_active' => true]);
        $template->checkpoints()->attach($this->checkpoint("{$code}-1")->id, ['order' => 1, 'is_required' => true, 'estimated_minutes' => 10]);
        $template->checkpoints()->attach($this->checkpoint("{$code}-2")->id, ['order' => 2, 'is_required' => false, 'estimated_minutes' => 20]);

        return $template;
    }

    public function test_the_typical_route_is_saved_on_create_and_update(): void
    {
        $a = $this->checkpoint('CK1');
        $b = $this->checkpoint('CK2');

        $this->asAdmin()->post('/admin/checkpoint-templates', [
            'code' => 'TMPL-ARR-AIR-TH', 'name' => 'Arrival', 'movement_type' => 'arrival',
            'from_location' => 'Airport', 'to_location' => 'Team Hotel', 'estimated_duration_minutes' => 15,
            'checkpoints' => [
                ['checkpoint_id' => $b->id, 'order' => 1, 'is_required' => true, 'estimated_minutes' => 5],
                ['checkpoint_id' => $a->id, 'order' => 2, 'is_required' => false, 'estimated_minutes' => 10],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $template = CheckpointTemplate::where('code', 'TMPL-ARR-AIR-TH')->firstOrFail();
        $this->assertSame(['Airport', 'Team Hotel'], [$template->from_location, $template->to_location]);
        $this->assertSame(['CK2', 'CK1'], $template->checkpoints()->get()->pluck('code')->all());

        $this->asAdmin()->put("/admin/checkpoint-templates/{$template->id}", [
            'code' => 'TMPL-ARR-AIR-TH', 'name' => 'Arrival', 'movement_type' => 'arrival',
            'from_location' => 'Airport', 'to_location' => null,
            'checkpoints' => [['checkpoint_id' => $a->id, 'order' => 1, 'is_required' => true, 'estimated_minutes' => null]],
        ])->assertSessionHasNoErrors();

        $this->assertNull($template->refresh()->to_location);
        $this->assertSame(['CK1'], $template->checkpoints()->get()->pluck('code')->all());
    }

    public function test_a_template_is_duplicated_in_its_event_with_its_steps_and_route(): void
    {
        $source = $this->template($this->event);

        $this->asAdmin()->post("/admin/checkpoint-templates/{$source->id}/duplicate")
            ->assertRedirect()->assertSessionHas('success', 'Duplicated as Arrival steps (copy) (TMPL-ARR-2).');

        $copy = CheckpointTemplate::where('code', 'TMPL-ARR-2')->firstOrFail();
        $this->assertSame($this->event->id, $copy->event_id);
        $this->assertSame(['Airport', 'Team Hotel'], [$copy->from_location, $copy->to_location]);
        $this->assertSame(
            $source->checkpoints()->get()->map(fn ($c) => [$c->id, (int) $c->pivot->order, (bool) $c->pivot->is_required, (int) $c->pivot->estimated_minutes])->all(),
            $copy->checkpoints()->get()->map(fn ($c) => [$c->id, (int) $c->pivot->order, (bool) $c->pivot->is_required, (int) $c->pivot->estimated_minutes])->all(),
        );
        $this->assertDatabaseHas('audit_logs', ['action' => 'Checkpoint template duplicated', 'target' => 'TMPL-ARR-2']);
    }

    public function test_a_template_of_another_event_is_not_duplicated(): void
    {
        $other = $this->template($this->createEvent(), 'TMPL-OTHER');

        $this->asAdmin()->post("/admin/checkpoint-templates/{$other->id}/duplicate")->assertNotFound();
        $this->assertSame(1, CheckpointTemplate::count());
    }

    public function test_the_library_lists_the_movement_templates_using_each_checkpoint_template(): void
    {
        $steps = $this->template($this->event);
        foreach (['Team Arrival', 'Late Arrival'] as $i => $name) {
            $mt = MovementTemplate::create(['event_id' => $this->event->id, 'code' => "MVT-{$i}", 'name' => $name, 'scenario_type' => 'arrival_day', 'is_active' => true]);
            $mt->legs()->create(['order' => 1, 'leg_type' => 'arrival', 'checkpoint_template_id' => $steps->id]);
            $mt->legs()->create(['order' => 2, 'leg_type' => 'arrival', 'checkpoint_template_id' => $steps->id]);
        }
        MovementTemplate::where('code', 'MVT-1')->first()->delete();

        $this->asAdmin()->get('/library')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('checkpointTemplates.0.from_location', 'Airport')
            ->where('checkpointTemplates.0.used_in', [['id' => MovementTemplate::where('code', 'MVT-0')->value('id'), 'name' => 'Team Arrival']]));
    }
}
