<?php

namespace Tests\Feature;

use App\Models\MovementTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class MovementTemplateDeleteTest extends TestCase
{
    use CreatesOperationsFixtures;
    use RefreshDatabase;

    public function test_a_template_in_use_is_kept_and_the_error_counts_plans_without_movements(): void
    {
        $event = $this->createEvent();
        $template = MovementTemplate::create(['event_id' => $event->id, 'code' => 'MVT-T', 'name' => 'Match Day to Hotel']);

        $withMovement = $this->createPlan($event);
        $withMovement->update(['movement_template_id' => $template->id]);
        $this->createMovement($event, $withMovement, $this->createTeam($event));
        $this->createPlan($event)->update(['movement_template_id' => $template->id]);

        $this->actingAs($this->createUserWithRole('admin'))
            ->delete("/admin/movement-templates/{$template->id}")
            ->assertSessionHas('error', 'Cannot delete template. It is used in 2 plan(s). 1 of them have no movements.');

        $this->assertModelExists($template);
    }

    public function test_an_unused_template_is_deleted(): void
    {
        $event = $this->createEvent();
        $template = MovementTemplate::create(['event_id' => $event->id, 'code' => 'MVT-U', 'name' => 'Unused']);

        $this->actingAs($this->createUserWithRole('admin'))
            ->delete("/admin/movement-templates/{$template->id}")
            ->assertSessionHas('success');

        $this->assertModelMissing($template);
    }
}
