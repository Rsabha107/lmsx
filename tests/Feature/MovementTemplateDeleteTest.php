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

    public function test_a_template_in_use_waits_for_another_admins_approval(): void
    {
        $event = $this->createEvent();
        $template = MovementTemplate::create(['event_id' => $event->id, 'code' => 'MVT-T', 'name' => 'Match Day to Hotel']);

        $withMovement = $this->createPlan($event);
        $withMovement->update(['movement_template_id' => $template->id]);
        $this->createMovement($event, $withMovement, $this->createTeam($event));
        // A plan already in the recycle bin still counts as using it.
        $deletedPlan = $this->createPlan($event);
        $deletedPlan->update(['movement_template_id' => $template->id]);
        $deletedPlan->delete();
        $withMovement->forceFill(['movement_template_id' => null])->save();

        $this->actingAs($this->createUserWithRole('admin'))
            ->delete("/admin/movement-templates/{$template->id}")
            ->assertSessionHas('success', fn ($message) => str_starts_with($message, 'Sent for approval: 1 movement template.'));

        $this->assertNotSoftDeleted($template);
        $this->assertDatabaseHas('deletions', ['kind' => 'movement_templates', 'status' => 'pending', 'trigger' => 'in_use']);
    }

    public function test_a_template_used_by_a_plan_cannot_be_deleted(): void
    {
        $event = $this->createEvent();
        $template = MovementTemplate::create(['event_id' => $event->id, 'code' => 'MVT-L', 'name' => 'Live']);
        foreach ([true, false] as $withMovement) {
            $plan = $this->createPlan($event);
            $plan->update(['movement_template_id' => $template->id]);
            if ($withMovement) {
                $this->createMovement($event, $plan, $this->createTeam($event));
            }
        }

        $this->actingAs($this->createUserWithRole('admin'))
            ->delete("/admin/movement-templates/{$template->id}")
            ->assertSessionHas('error', 'Cannot delete template. It is used in 2 plan(s). 1 of them have no movements.');

        $this->assertNotSoftDeleted($template);
        $this->assertDatabaseCount('deletions', 0);
    }

    public function test_a_plan_made_while_the_request_waits_stops_the_approval(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $event = $this->createEvent();
        $template = MovementTemplate::create(['event_id' => $event->id, 'code' => 'MVT-W', 'name' => 'Waiting']);
        $deletedPlan = $this->createPlan($event);
        $deletedPlan->update(['movement_template_id' => $template->id]);
        $deletedPlan->delete();

        $this->actingAs($this->createUserWithRole('admin'))->delete("/admin/movement-templates/{$template->id}");
        $request = \App\Models\Deletion::where('kind', 'movement_templates')->firstOrFail();
        $this->createPlan($event)->update(['movement_template_id' => $template->id]);

        $this->actingAs($this->createUserWithRole('admin'))
            ->post("/recycle-bin/{$request->id}/approve")
            ->assertSessionHas('error', 'Cannot delete template. It is used in 1 plan(s). 1 of them have no movements.');

        $this->assertNotSoftDeleted($template);
        $this->assertSame('pending', $request->refresh()->status);
    }

    public function test_an_unused_template_goes_to_the_recycle_bin(): void
    {
        $event = $this->createEvent();
        $template = MovementTemplate::create(['event_id' => $event->id, 'code' => 'MVT-U', 'name' => 'Unused']);

        $this->actingAs($this->createUserWithRole('admin'))
            ->delete("/admin/movement-templates/{$template->id}")
            ->assertSessionHas('success');

        $this->assertSoftDeleted($template);
    }
}
