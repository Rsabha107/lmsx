<?php

namespace Tests\Feature;

use App\Models\FleetProvider;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class AdoptUnownedFleetDataTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_dry_run_changes_nothing_and_apply_hands_unowned_data_to_the_provider(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $event = $this->createEvent();
        $gwc = FleetProvider::create(['code' => 'GWC2', 'name' => 'GWC Two', 'status' => 'active']);
        $agency = User::factory()->create();
        $agency->assignRole('agency');

        $movement = $this->createMovement($event, $this->createPlan($event), $this->createTeam($event));
        DB::table('movements')->where('id', $movement->id)->update(['fleet_provider_id' => null]);
        DB::table('events')->where('id', $event->id)->update(['fleet_provider_id' => null]);

        $this->artisan('fleet:adopt-unowned', ['provider' => 'GWC2'])->assertSuccessful();

        $this->assertNull(DB::table('movements')->where('id', $movement->id)->value('fleet_provider_id'));
        $this->assertNull($agency->refresh()->fleet_provider_id);

        $this->artisan('fleet:adopt-unowned', ['provider' => (string) $gwc->id, '--apply' => true])->assertSuccessful();

        $this->assertSame($gwc->id, (int) DB::table('movements')->where('id', $movement->id)->value('fleet_provider_id'));
        $this->assertSame($gwc->id, (int) DB::table('events')->where('id', $event->id)->value('fleet_provider_id'));
        $this->assertSame($gwc->id, $agency->refresh()->fleet_provider_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Unowned fleet data adopted', 'target' => 'GWC2']);
    }

    public function test_an_unknown_provider_is_refused(): void
    {
        $this->artisan('fleet:adopt-unowned', ['provider' => 'NOPE'])->assertFailed();
    }
}
