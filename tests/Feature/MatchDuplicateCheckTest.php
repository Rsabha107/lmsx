<?php

namespace Tests\Feature;

use App\Models\CheckpointTemplate;
use App\Models\Event;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class MatchDuplicateCheckTest extends TestCase
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

    private function sequence(string $code): CheckpointTemplate
    {
        return CheckpointTemplate::create([
            'event_id' => $this->event->id, 'code' => $code, 'name' => $code, 'movement_type' => 'match', 'is_active' => true,
        ]);
    }

    public function test_same_match_with_a_different_checkpoint_sequence_is_not_a_duplicate(): void
    {
        $team = $this->createTeam($this->event);
        $match = GameMatch::create(['event_id' => $this->event->id, 'match_number' => 'M1', 'team1_id' => $team->id, 'kick_off' => now()->addDay()]);
        $toStadium = $this->sequence('MD-TO-STADIUM');
        $toHotel = $this->sequence('MD-TO-HOTEL');

        $this->createMovement($this->event, $this->createPlan($this->event), $team, [
            'kind' => 'match', 'match_id' => $match->id, 'checkpoint_template_id' => $toStadium->id,
        ]);

        $check = fn (CheckpointTemplate $sequence) => $this->planner()->postJson('/api/check-duplicate-bulk', ['checks' => [[
            'key' => '0', 'kind' => 'match', 'team_id' => $team->id, 'match_id' => $match->id,
            'checkpoint_template_id' => $sequence->id,
        ]]])->assertOk()->json('results.0.exists');

        $this->assertFalse($check($toHotel));
        $this->assertTrue($check($toStadium));
    }
}
