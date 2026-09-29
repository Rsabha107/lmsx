<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Event;
use App\Models\Movement;
use App\Models\User;
use App\Services\ConflictDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class ConflictResolveTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->event = $this->createEvent();
    }

    /** Someone who manages plans but has no agency grant. */
    private function planner(): static
    {
        $names = ['plans.view', 'plans.manage', 'movements.view', 'movements.view-all-functional-areas', 'jobs.view'];
        foreach ($names as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Permission::firstOrCreate(['name' => 'movements.assign-crew', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'planner', 'guard_name' => 'web'])->syncPermissions($names);

        $user = User::factory()->create();
        $user->assignRole('planner');
        $user->events()->attach($this->event);

        return $this->actingAs($user)->withSession(['active_event_id' => $this->event->id]);
    }

    private function movement(string $start, string $end, array $attrs = []): Movement
    {
        return $this->createMovement($this->event, $this->createPlan($this->event), $this->createTeam($this->event), $attrs + [
            'window_start' => $start, 'window_end' => $end,
        ]);
    }

    public function test_crew_options_mark_who_is_busy_and_who_is_free(): void
    {
        $busy = Driver::create(['name' => 'Busy Driver', 'status' => 'available']);
        $free = Driver::create(['name' => 'Free Driver', 'status' => 'available']);
        $off = Driver::create(['name' => 'Off Driver', 'status' => 'off']);

        $this->movement('2026-11-27 12:00', '2026-11-27 14:00', ['driver_id' => $busy->id, 'code' => 'M-OTHER']);
        $target = $this->movement('2026-11-27 13:00', '2026-11-27 15:00');

        $drivers = collect($this->planner()->getJson("/movements/{$target->id}/crew-options")->assertOk()->json('drivers'))->keyBy('id');

        $this->assertTrue($drivers[$free->id]['free']);
        $this->assertFalse($drivers[$busy->id]['free']);
        $this->assertStringContainsString('M-OTHER', $drivers[$busy->id]['reason']);
        $this->assertSame('Marked off', $drivers[$off->id]['reason']);
    }

    public function test_a_planner_can_reassign_crew_from_the_resolve_panel(): void
    {
        $driver = Driver::create(['name' => 'New Driver', 'status' => 'available']);
        $movement = $this->movement('2026-11-27 13:00', '2026-11-27 15:00');

        $this->planner()
            ->patch("/movements/{$movement->id}/crew", ['vehicle_id' => null, 'driver_id' => $driver->id, 'field_supervisor_id' => null])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame($driver->id, $movement->refresh()->driver_id);
    }

    public function test_an_accepted_conflict_is_marked_and_can_be_reopened(): void
    {
        $driver = Driver::create(['name' => 'Mia', 'status' => 'available']);
        $this->movement('2026-11-27 12:00', '2026-11-27 14:00', ['driver_id' => $driver->id]);
        $this->movement('2026-11-27 13:00', '2026-11-27 15:00', ['driver_id' => $driver->id]);

        $conflict = collect(app(ConflictDetectionService::class)->forEvent($this->event->id))
            ->firstWhere('type', 'Driver Double-Booked');
        $this->assertNull($conflict['accepted']);

        $this->planner()->post('/conflicts/accept', ['conflict_id' => $conflict['id'], 'reason' => 'Ops lead approved'])
            ->assertRedirect();

        $accepted = collect(app(ConflictDetectionService::class)->forEvent($this->event->id))->firstWhere('id', $conflict['id']);
        $this->assertSame('Ops lead approved', $accepted['accepted']['reason']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Conflict accepted', 'target' => $conflict['id']]);

        $this->planner()->delete('/conflicts/accept', ['conflict_id' => $conflict['id']])->assertRedirect();

        $reopened = collect(app(ConflictDetectionService::class)->forEvent($this->event->id))->firstWhere('id', $conflict['id']);
        $this->assertNull($reopened['accepted']);
    }

    public function test_accepting_needs_a_reason(): void
    {
        $this->planner()->post('/conflicts/accept', ['conflict_id' => 'DDB-1-2', 'reason' => ''])
            ->assertSessionHasErrors('reason');
    }
}
