<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Movement;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** A completed job's crew is the record of who ran it: Crew Assignment and Planning can't change it. */
class CompletedJobCrewLockTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function agency(Event $event): static
    {
        return $this->actingAs($this->createProviderUser('agency'))->withSession(['active_event_id' => $event->id]);
    }

    /** @return array{0: Movement, 1: Event} */
    private function completedMovement(array $attrs = []): array
    {
        $event = $this->createEvent();
        $team = $this->createTeam($event);
        $movement = $this->createMovement($event, $this->createPlan($event), $team, $attrs + [
            'window_start' => '2026-10-14 14:00', 'window_end' => '2026-10-14 14:30',
        ]);
        $this->createJob($event, $movement, $team, ['status' => 'completed', 'completed_at' => '2026-10-14 14:34:00']);

        return [$movement, $event];
    }

    public function test_crew_assignment_refuses_to_change_a_completed_jobs_crew(): void
    {
        [$movement, $event] = $this->completedMovement();
        $driver = $this->createDriver($event);

        $this->agency($event)->patch("/movements/{$movement->id}/crew", [
            'vehicle_id' => null, 'driver_id' => $driver->id, 'field_supervisor_id' => null,
        ])->assertSessionHasErrors(['crew' => "{$movement->code}: its job is completed, so the crew can no longer be changed."]);

        $this->assertNull($movement->refresh()->driver_id);
    }

    public function test_saving_an_unchanged_completed_movement_is_fine(): void
    {
        [$movement, $event] = $this->completedMovement();

        $this->agency($event)->patch("/movements/{$movement->id}/crew", [
            'vehicle_id' => null, 'driver_id' => null, 'field_supervisor_id' => null,
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'No changes to save.');
    }

    public function test_a_bulk_save_skips_the_completed_movement_and_saves_the_rest(): void
    {
        [$done, $event] = $this->completedMovement();
        $open = $this->createMovement($event, $this->createPlan($event), $this->createTeam($event), [
            'window_start' => '2026-10-14 18:00', 'window_end' => '2026-10-14 19:00',
        ]);
        $driver = $this->createDriver($event);

        $this->agency($event)->patch('/movements/crew', ['movements' => [
            ['id' => $done->id, 'vehicle_id' => null, 'driver_id' => $driver->id, 'field_supervisor_id' => null],
            ['id' => $open->id, 'vehicle_id' => null, 'driver_id' => $driver->id, 'field_supervisor_id' => null],
        ]])->assertSessionHasErrors('crew')->assertSessionHas('success', "Crew updated for {$open->code}.");

        $this->assertNull($done->refresh()->driver_id);
        $this->assertSame($driver->id, $open->refresh()->driver_id);
    }

    public function test_planning_refuses_a_crew_change_but_saves_other_edits(): void
    {
        [$movement, $event] = $this->completedMovement();
        $driver = $this->createDriver($event);
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)->withSession(['active_event_id' => $event->id])
            ->put("/movements/{$movement->id}", ['driver_id' => $driver->id, 'units' => [], 'supervisors' => [], 'notes' => 'Changed'])
            ->assertSessionHasErrors('crew');
        $this->assertNull($movement->refresh()->driver_id);
        $this->assertNull($movement->notes);

        $this->actingAs($admin)->withSession(['active_event_id' => $event->id])
            ->put("/movements/{$movement->id}", ['driver_id' => null, 'units' => [], 'supervisors' => [], 'notes' => 'Changed'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Changed', $movement->refresh()->notes);
    }

    public function test_the_page_marks_the_movement_done_with_its_time(): void
    {
        [$movement, $event] = $this->completedMovement();

        $this->agency($event)->get('/crew-assignment?date=2026-10-14')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('movements.0.id', $movement->id)
                ->where('movements.0.job_status', 'completed')
                ->where('movements.0.job_done_at', '14:34'));
    }
}
