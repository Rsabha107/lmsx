<?php

namespace Tests\Feature;

use App\Models\Checkpoint;
use App\Models\CheckpointTemplate;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Library → Checkpoints: the bulk actions and the "used in" list. */
class CheckpointLibraryTest extends TestCase
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

    private function checkpoint(string $code, array $attrs = []): Checkpoint
    {
        return Checkpoint::create($attrs + ['code' => $code, 'name' => "Step {$code}", 'type' => 'dispatch', 'category' => 'Logistics', 'capture_method' => 'manual', 'is_active' => true]);
    }

    private function template(Event $event, string $code = 'CT-1'): CheckpointTemplate
    {
        return CheckpointTemplate::create(['event_id' => $event->id, 'code' => $code, 'name' => "Template {$code}", 'movement_type' => 'arrival', 'is_active' => true]);
    }

    public function test_the_category_of_several_checkpoints_is_changed_at_once(): void
    {
        $a = $this->checkpoint('CK1');
        $b = $this->checkpoint('CK2', ['category' => 'A&D']);
        $c = $this->checkpoint('CK3');

        $this->asAdmin()->patch('/admin/checkpoints/bulk-category', ['ids' => [$a->id, $b->id], 'category' => 'A&D'])
            ->assertRedirect()->assertSessionHas('success', 'Category set to A&D for 1 checkpoint(s).');

        $this->assertSame('A&D', $a->refresh()->category);
        $this->assertSame('Logistics', $c->refresh()->category);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Checkpoints category changed']);

        $this->asAdmin()->patch('/admin/checkpoints/bulk-category', ['ids' => [$a->id], 'category' => 'Nonsense'])
            ->assertSessionHasErrors('category');
    }

    public function test_checkpoints_are_appended_to_a_template_in_code_order_skipping_ones_already_in_it(): void
    {
        $template = $this->template($this->event);
        $first = $this->checkpoint('CK1');
        $template->checkpoints()->attach($first->id, ['order' => 1, 'is_required' => true]);
        $ten = $this->checkpoint('CK10');
        $two = $this->checkpoint('CK2');

        $this->asAdmin()->post('/admin/checkpoints/add-to-template', ['template_id' => $template->id, 'ids' => [$ten->id, $first->id, $two->id]])
            ->assertRedirect()->assertSessionHas('success', "Added 2 checkpoint(s) to {$template->name}. 1 already in it.");

        $this->assertSame(['CK1', 'CK2', 'CK10'], $template->checkpoints()->get()->pluck('code')->all());
        $this->assertSame([1, 2, 3], $template->checkpoints()->get()->pluck('pivot.order')->map(fn ($o) => (int) $o)->all());

        $this->asAdmin()->post('/admin/checkpoints/add-to-template', ['template_id' => $template->id, 'ids' => [$two->id]])
            ->assertSessionHas('error', "Nothing added: they are all in {$template->name} already.");
    }

    public function test_a_template_of_another_event_is_refused(): void
    {
        $other = $this->template($this->createEvent(), 'CT-OTHER');
        $cp = $this->checkpoint('CK1');

        $this->asAdmin()->post('/admin/checkpoints/add-to-template', ['template_id' => $other->id, 'ids' => [$cp->id]])
            ->assertSessionHasErrors(['template_id' => 'Pick a checkpoint template of the active event.']);
        $this->assertSame(0, $other->checkpoints()->count());
    }

    public function test_only_admins_can_use_the_bulk_actions(): void
    {
        $cp = $this->checkpoint('CK1');
        $agency = $this->createProviderUser('agency');

        $this->actingAs($agency)->patch('/admin/checkpoints/bulk-category', ['ids' => [$cp->id], 'category' => 'A&D'])->assertForbidden();
        $this->assertSame('Logistics', $cp->refresh()->category);
    }

    public function test_the_library_lists_the_templates_each_checkpoint_is_used_in(): void
    {
        $cp = $this->checkpoint('CK1');
        $mine = $this->template($this->event, 'CT-MINE');
        $theirs = $this->template($this->createEvent(), 'CT-THEIRS');
        $mine->checkpoints()->attach($cp->id, ['order' => 1, 'is_required' => true]);
        $theirs->checkpoints()->attach($cp->id, ['order' => 1, 'is_required' => true]);

        $this->asAdmin()->get('/library')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('checkpoints.0.usage_count', 2)
            ->where('checkpoints.0.used_in', fn ($list) => collect($list)->pluck('name')->sort()->values()->all() === ['Template CT-MINE', 'Template CT-THEIRS'])
            ->missing('checkpoints.0.checkpoint_templates'));
    }
}
