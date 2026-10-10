<?php

namespace Tests\Feature;

use App\Models\FleetProvider;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class AssignOrphansToProviderTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_unowned_rows_go_to_the_provider_and_owned_rows_stay(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $gwc = FleetProvider::create(['code' => 'GWC', 'name' => 'GWC', 'status' => 'active']);
        $other = FleetProvider::create(['code' => 'OTH', 'name' => 'Other', 'status' => 'active']);

        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);
        $orphan = $this->createMovement($event, $plan, $team);
        $orphan->forceFill(['fleet_provider_id' => null])->save();
        $owned = $this->createMovement($event, $plan, $team);
        $owned->forceFill(['fleet_provider_id' => $other->id])->save();

        $agency = User::factory()->create(['fleet_provider_id' => null]);
        $agency->assignRole('agency');

        $this->artisan('fleet:assign-orphans', ['provider' => 'GWC', '--dry-run' => true])->assertSuccessful();
        $this->assertNull($orphan->refresh()->fleet_provider_id);

        $this->artisan('fleet:assign-orphans', ['provider' => 'GWC'])->assertSuccessful();

        $this->assertSame($gwc->id, $orphan->refresh()->fleet_provider_id);
        $this->assertSame($other->id, $owned->refresh()->fleet_provider_id);
        $this->assertSame($gwc->id, $agency->refresh()->fleet_provider_id);
    }

    public function test_an_unknown_provider_code_changes_nothing(): void
    {
        $this->artisan('fleet:assign-orphans', ['provider' => 'NOPE'])->assertFailed();
    }
}
