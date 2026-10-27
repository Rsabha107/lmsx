<?php

namespace Tests\Feature;

use App\Models\CheckpointTemplate;
use App\Models\MovementTemplate;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** The "Save changes to a template in use?" numbers: jobs that keep their steps, running ones, and movements that follow. */
class TemplateImpactTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_it_counts_jobs_running_jobs_and_movements_still_without_a_job(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $steps = CheckpointTemplate::create(['event_id' => $event->id, 'code' => 'TMPL-AIR', 'name' => 'Arrival', 'movement_type' => 'arrival', 'is_active' => true]);
        $route = MovementTemplate::create(['event_id' => $event->id, 'code' => 'MVT-ARR', 'name' => 'Team Arrival']);
        $plan = $this->createPlan($event);
        $plan->update(['movement_template_id' => $route->id]);

        $movement = fn (array $a = []) => $this->createMovement($event, $plan, $team, ['checkpoint_template_id' => $steps->id] + $a);
        foreach (['completed', 'in-progress', 'dispatched', 'cancelled'] as $status) {
            $m = $movement();
            $job = $this->createJob($event, $m, $team, ['status' => $status]);
            $m->update(['job_id' => $job->id]);
        }
        $movement();                            // waiting for its job
        $movement(['status' => 'cancelled']);   // not counted

        $admin = $this->createUserWithRole('admin');
        $expected = ['jobs' => 3, 'running' => 2, 'waiting' => 1];
        $this->actingAs($admin)->getJson("/admin/checkpoint-templates/{$steps->id}/impact")->assertExactJson($expected);
        $this->actingAs($admin)->getJson("/admin/movement-templates/{$route->id}/impact")->assertExactJson($expected);

        $this->actingAs($this->createUserWithRole('transport'))->getJson("/admin/checkpoint-templates/{$steps->id}/impact")->assertForbidden();
    }
}
