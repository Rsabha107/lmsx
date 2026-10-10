<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class PlanningSupervisorSourceTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_planning_offers_the_same_supervisors_as_crew_assignment(): void
    {
        $event = $this->createEvent();
        $supervisor = $this->createProviderUser('ground_control');
        $notASupervisor = User::factory()->create(['name' => 'Plain User']);

        $this->actingAs($this->createUserWithRole('admin'))->withSession(['active_event_id' => $event->id])
            ->get('/plans')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('supervisors', function ($list) use ($supervisor, $notASupervisor) {
                $ids = collect($list)->pluck('id');

                return $ids->contains($supervisor->id) && ! $ids->contains($notASupervisor->id);
            }));
    }

    public function test_editing_a_movement_refuses_someone_who_cannot_work_jobs(): void
    {
        $event = $this->createEvent();
        $movement = $this->createMovement($event, $this->createPlan($event), $this->createTeam($event));
        $notASupervisor = User::factory()->create(['fleet_provider_id' => $this->fixtureProvider()->id]);
        $supervisor = $this->createProviderUser('ground_control');
        $admin = $this->actingAs($this->createUserWithRole('admin'))->withSession(['active_event_id' => $event->id]);

        $admin->put("/movements/{$movement->id}", ['field_supervisor_id' => $notASupervisor->id])
            ->assertSessionHasErrors('supervisor_id');
        $this->assertNull($movement->refresh()->field_supervisor_id);

        $admin->put("/movements/{$movement->id}", ['field_supervisor_id' => $supervisor->id])->assertSessionHasNoErrors();
        $this->assertSame($supervisor->id, $movement->refresh()->field_supervisor_id);
    }
}
