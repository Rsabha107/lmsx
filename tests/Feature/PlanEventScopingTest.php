<?php

namespace Tests\Feature;

use App\Models\CheckpointTemplate;
use App\Models\Event;
use App\Models\MovementTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class PlanEventScopingTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    private Event $other;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = $this->createEvent();
        $this->other = $this->createEvent();

        $names = ['plans.view', 'plans.manage'];
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'planner', 'guard_name' => 'web'])->syncPermissions($names);

        $this->user = User::factory()->create();
        $this->user->assignRole('planner');
        $this->user->events()->attach($this->event);
    }

    private function planner(?int $activeEventId = null): static
    {
        return $this->actingAs($this->user)->withSession(['active_event_id' => $activeEventId ?? $this->event->id]);
    }

    private function templates(Event $event, string $suffix): array
    {
        return [
            CheckpointTemplate::create(['event_id' => $event->id, 'code' => "CT-{$suffix}", 'name' => "Sequence {$suffix}", 'movement_type' => 'transfer', 'is_active' => true]),
            MovementTemplate::create(['event_id' => $event->id, 'code' => "MVT-{$suffix}", 'name' => "Template {$suffix}", 'is_active' => true]),
        ];
    }

    public function test_another_events_records_are_refused_by_id(): void
    {
        $plan = $this->createPlan($this->other);
        $movement = $this->createMovement($this->other, $plan, $this->createTeam($this->other));
        [$checkpointTemplate, $movementTemplate] = $this->templates($this->other, 'B');

        $this->planner()->get("/plans/{$plan->id}")->assertForbidden();
        $this->planner()->getJson("/plans/{$plan->id}/delete-check")->assertForbidden();
        $this->planner()->put("/plans/{$plan->id}", ['name' => 'Taken', 'date' => '2026-10-20', 'status' => 'active'])->assertForbidden();
        $this->planner()->put("/plans/{$plan->id}/status", ['status' => 'cancelled'])->assertForbidden();
        $this->planner()->post("/plans/{$plan->id}/generate-jobs", ['movement_ids' => [$movement->id]])->assertForbidden();
        $this->planner()->post("/plans/{$plan->id}/movements", [])->assertForbidden();
        $this->planner()->delete("/plans/{$plan->id}")->assertForbidden();

        $this->planner()->getJson("/movements/{$movement->id}/checkpoints")->assertForbidden();
        $this->planner()->put("/movements/{$movement->id}", ['notes' => 'Taken'])->assertForbidden();
        $this->planner()->put("/movements/{$movement->id}/checkpoint-template", ['checkpoint_template_id' => $checkpointTemplate->id])->assertForbidden();
        $this->planner()->delete("/movements/{$movement->id}")->assertForbidden();

        $this->planner()->getJson("/api/checkpoint-templates/{$checkpointTemplate->id}")->assertForbidden();
        $this->planner()->getJson("/api/movement-templates/{$movementTemplate->id}")->assertForbidden();

        $this->assertNotSoftDeleted($plan);
        $this->assertNotSoftDeleted($movement);
        $this->assertSame('Test Plan', $plan->refresh()->name);
        $this->assertNull($movement->refresh()->notes);
    }

    public function test_the_active_events_records_still_work(): void
    {
        $plan = $this->createPlan($this->event);
        $movement = $this->createMovement($this->event, $plan, $this->createTeam($this->event));
        [$checkpointTemplate, $movementTemplate] = $this->templates($this->event, 'A');

        $this->planner()->getJson("/plans/{$plan->id}/delete-check")->assertOk();
        $this->planner()->getJson("/movements/{$movement->id}/checkpoints")->assertOk();
        $this->planner()->getJson("/api/checkpoint-templates/{$checkpointTemplate->id}")->assertOk();
        $this->planner()->getJson("/api/movement-templates/{$movementTemplate->id}")->assertOk();
        $this->planner()->put("/movements/{$movement->id}", ['notes' => 'Ours'])->assertSessionHasNoErrors();

        $this->assertSame('Ours', $movement->refresh()->notes);
    }

    public function test_lists_and_bulk_actions_leave_other_events_alone(): void
    {
        [$ownCheckpoints, $ownTemplate] = $this->templates($this->event, 'A');
        $this->templates($this->other, 'B');
        $foreignTeam = $this->createTeam($this->other);
        $foreign = $this->createMovement($this->other, $this->createPlan($this->other), $foreignTeam);

        $this->planner()->getJson('/api/checkpoint-templates')
            ->assertOk()
            ->assertJsonPath('templates.*.id', [$ownCheckpoints->id]);
        $this->planner()->getJson('/api/movement-templates')
            ->assertOk()
            ->assertJsonPath('templates.*.id', [$ownTemplate->id]);

        $this->planner()->delete('/movements/bulk-delete', ['ids' => [$foreign->id]]);
        $this->assertNotSoftDeleted($foreign);

        $this->planner()->postJson('/api/check-duplicate', ['kind' => 'transfer', 'team_id' => $foreignTeam->id])
            ->assertJsonValidationErrors('team_id');
    }

    public function test_plans_page_sends_no_data_without_a_reachable_event(): void
    {
        $this->createPlan($this->event);
        $this->createPlan($this->other);

        $this->actingAs($this->user)->get('/plans')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('activeEvent', null)->missing('plans'));

        // An event the user was taken off keeps no access through the session.
        $this->planner($this->other->id)->get('/plans')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('activeEvent', null)->missing('plans'));

        $this->planner()->get('/plans')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('plans', 1));
    }
}
