<?php

namespace Tests\Feature;

use App\Models\CheckpointTemplate;
use App\Models\Event;
use App\Models\MovementTemplate;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Library → Movement Templates: Duplicate and the "used in" counts. */
class MovementTemplateLibraryTest extends TestCase
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

    private function template(Event $event, string $code = 'MVT-ARR'): MovementTemplate
    {
        $steps = CheckpointTemplate::create(['event_id' => $event->id, 'code' => "CT-{$code}", 'name' => "Steps {$code}", 'movement_type' => 'arrival', 'is_active' => true]);
        $template = MovementTemplate::create(['event_id' => $event->id, 'code' => $code, 'name' => 'Team Arrival', 'scenario_type' => 'arrival_day', 'total_legs' => 1, 'estimated_duration_minutes' => 90, 'is_active' => true]);
        $template->legs()->create(['order' => 1, 'leg_type' => 'arrival', 'checkpoint_template_id' => $steps->id, 'from_location' => 'Airport', 'to_location' => 'Team Hotel', 'vehicle_type' => 'bus']);

        return $template;
    }

    public function test_a_template_is_duplicated_in_its_event_with_its_legs_on_the_same_checkpoint_templates(): void
    {
        $source = $this->template($this->event);

        $this->asAdmin()->post("/admin/movement-templates/{$source->id}/duplicate")
            ->assertRedirect()->assertSessionHas('success', 'Duplicated as Team Arrival (copy) (MVT-ARR-2).');

        $copy = MovementTemplate::where('code', 'MVT-ARR-2')->firstOrFail();
        $this->assertSame($this->event->id, $copy->event_id);
        $this->assertSame('arrival_day', $copy->scenario_type);
        $this->assertSame(90, (int) $copy->estimated_duration_minutes);
        $this->assertSame(
            $source->legs->map->only(['order', 'checkpoint_template_id', 'from_location', 'to_location', 'vehicle_type'])->all(),
            $copy->legs()->get()->map->only(['order', 'checkpoint_template_id', 'from_location', 'to_location', 'vehicle_type'])->all(),
        );
        $this->assertSame(1, CheckpointTemplate::where('event_id', $this->event->id)->count(), 'no checkpoint template is copied');
        $this->assertDatabaseHas('audit_logs', ['action' => 'Movement template duplicated', 'target' => 'MVT-ARR-2']);

        $this->asAdmin()->post("/admin/movement-templates/{$source->id}/duplicate");
        $this->assertTrue(MovementTemplate::where('code', 'MVT-ARR-3')->where('name', 'Team Arrival (copy) (2)')->exists());
    }

    public function test_a_template_of_another_event_is_not_duplicated(): void
    {
        $other = $this->template($this->createEvent(), 'MVT-OTHER');

        $this->asAdmin()->post("/admin/movement-templates/{$other->id}/duplicate")->assertNotFound();
        $this->assertSame(1, MovementTemplate::count());
    }

    public function test_the_library_counts_the_plans_and_movements_using_each_template(): void
    {
        $template = $this->template($this->event);
        $team = $this->createTeam($this->event);
        $plan = $this->createPlan($this->event);
        $plan->update(['movement_template_id' => $template->id]);
        $this->createMovement($this->event, $plan, $team);
        $this->createMovement($this->event, $plan, $team);
        $gone = $this->createMovement($this->event, $plan, $team);
        $gone->delete();

        $this->asAdmin()->get('/library')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('movementTemplates.0.plans_count', 1)
            ->where('movementTemplates.0.movements_count', 2));
    }
}
