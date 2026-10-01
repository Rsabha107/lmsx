<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class PlanDeleteTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = $this->createEvent();
    }

    private function planner(): static
    {
        $names = ['plans.view', 'plans.manage'];
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'planner', 'guard_name' => 'web'])->syncPermissions($names);

        $user = User::factory()->create();
        $user->assignRole('planner');
        $user->events()->attach($this->event);

        return $this->actingAs($user)->withSession(['active_event_id' => $this->event->id]);
    }

    public function test_delete_check_reports_live_movement_count(): void
    {
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $this->createMovement($this->event, $plan, $team);
        $this->createMovement($this->event, $plan, $team);

        $this->planner()->getJson("/plans/{$plan->id}/delete-check")
            ->assertOk()
            ->assertJson(['movements_count' => 2, 'jobs_count' => 0]);
    }

    public function test_plan_list_count_reflects_deleted_movements(): void
    {
        $plan = $this->createPlan($this->event);
        $plan->update(['movements_count' => 8]);
        $team = $this->createTeam($this->event);
        $kept = $this->createMovement($this->event, $plan, $team);
        $this->createMovement($this->event, $plan, $team)->delete();

        $this->planner()->get('/plans')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('plans.0.id', $plan->id)
                ->where('plans.0.movements_count', 1));
    }

    public function test_bulk_delete_needs_delete_typed_when_movements_exist(): void
    {
        $team = $this->createTeam($this->event);
        $withMovements = $this->createPlan($this->event);
        $movement = $this->createMovement($this->event, $withMovements, $team);
        $empty = $this->createPlan($this->event);

        $this->planner()->delete('/plans/bulk-delete', ['ids' => [$withMovements->id, $empty->id]])
            ->assertSessionHasErrors('confirm');
        $this->assertNotSoftDeleted($withMovements);

        $this->planner()->delete('/plans/bulk-delete', ['ids' => [$withMovements->id, $empty->id], 'confirm' => 'DELETE'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('plans.index'));

        $this->assertSoftDeleted($withMovements);
        $this->assertSoftDeleted($empty);
        $this->assertSoftDeleted($movement);
    }

    public function test_bulk_delete_of_empty_plans_needs_no_typing_and_skips_plans_with_jobs(): void
    {
        $team = $this->createTeam($this->event);
        $empty = $this->createPlan($this->event);
        $withJobs = $this->createPlan($this->event);
        $movement = $this->createMovement($this->event, $withJobs, $team);
        $movement->update(['job_id' => $this->createJob($this->event, $movement, $team)->job_id]);

        $this->planner()->delete('/plans/bulk-delete', ['ids' => [$empty->id, $withJobs->id]])
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($empty);
        $this->assertNotSoftDeleted($withJobs);
    }

    public function test_bulk_delete_ignores_plans_of_another_event(): void
    {
        $other = $this->createEvent();
        $foreign = $this->createPlan($other);

        $this->planner()->delete('/plans/bulk-delete', ['ids' => [$foreign->id]]);

        $this->assertNotSoftDeleted($foreign);
    }

    public function test_delete_requires_matching_plan_name(): void
    {
        $plan = $this->createPlan($this->event);
        $this->createMovement($this->event, $plan, $this->createTeam($this->event));

        $this->planner()->delete("/plans/{$plan->id}", ['confirm_name' => 'Wrong'])
            ->assertSessionHasErrors('confirm_name');

        $this->assertNotSoftDeleted($plan);
    }

    public function test_empty_plan_deletes_without_typed_name(): void
    {
        $plan = $this->createPlan($this->event);

        $this->planner()->delete("/plans/{$plan->id}")
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('plans.index'));

        $this->assertSoftDeleted($plan);
    }

    public function test_delete_removes_plan_and_its_movements(): void
    {
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $m1 = $this->createMovement($this->event, $plan, $team);
        $m2 = $this->createMovement($this->event, $plan, $team);

        $this->planner()->delete("/plans/{$plan->id}", ['confirm_name' => $plan->name])
            ->assertRedirect(route('plans.index'))
            ->assertSessionHasNoErrors();

        $this->assertSoftDeleted($plan);
        $this->assertSoftDeleted($m1);
        $this->assertSoftDeleted($m2);
    }

    public function test_delete_is_blocked_when_movements_have_jobs(): void
    {
        $plan = $this->createPlan($this->event);
        $team = $this->createTeam($this->event);
        $movement = $this->createMovement($this->event, $plan, $team);
        $job = $this->createJob($this->event, $movement, $team);
        $movement->update(['job_id' => $job->id]);

        $this->planner()->delete("/plans/{$plan->id}", ['confirm_name' => $plan->name])
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($plan);
        $this->assertNotSoftDeleted($movement);
    }
}
