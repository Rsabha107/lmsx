<?php

namespace Tests\Feature;

use App\Models\Movement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class MovementCodeTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_codes_use_the_trp_sequence_and_skip_soft_deleted_numbers(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);

        $this->assertSame('TRP-00001', Movement::formatCode(Movement::nextCodeNumber()));

        $this->createMovement($event, $plan, $team, ['code' => 'TRP-00007']);
        $this->createMovement($event, $plan, $team, ['code' => 'TRP-00009'])->delete();
        $this->createMovement($event, $plan, $team, ['code' => 'MV-OTHER']);

        $this->assertSame('TRP-00010', Movement::formatCode(Movement::nextCodeNumber()));
    }
}
