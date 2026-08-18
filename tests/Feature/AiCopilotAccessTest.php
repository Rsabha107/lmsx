<?php

namespace Tests\Feature;

use App\Ai\Agents\OperationsCopilotAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class AiCopilotAccessTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_guest_is_redirected_away_from_the_copilot(): void
    {
        $this->get('/ai')->assertRedirect('/login');
        $this->postJson('/ai/query', ['question' => 'hello'])->assertUnauthorized();
    }

    public function test_user_without_ai_use_permission_is_forbidden(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->get('/ai')->assertForbidden();
    }

    public function test_user_with_ai_use_permission_can_reach_the_page(): void
    {
        $user = $this->createUserWithRole('admin');

        $this->actingAs($user)->get('/ai')->assertOk();
    }

    public function test_query_without_active_event_returns_a_friendly_error(): void
    {
        $user = $this->createUserWithRole('admin');

        $response = $this->actingAs($user)->postJson('/ai/query', ['question' => 'what needs attention?']);

        $response->assertOk()->assertJson(['ok' => false]);
    }

    public function test_functional_area_scoped_user_only_gets_their_area_from_the_endpoint(): void
    {
        $event = $this->createEvent();
        $plan = $this->createPlan($event);
        $team = $this->createTeam($event);

        $logMovement = $this->createMovement($event, $plan, $team, ['functional_area' => 'LOG', 'delay_minutes' => 12]);
        $this->createJob($event, $logMovement, $team, ['functional_area' => 'LOG']);

        $andMovement = $this->createMovement($event, $plan, $team, ['functional_area' => 'AND', 'delay_minutes' => 9]);
        $this->createJob($event, $andMovement, $team, ['functional_area' => 'AND']);

        OperationsCopilotAgent::fake([
            new \Laravel\Ai\Responses\Data\ToolCall(id: 'call_1', name: 'GetDelayedMovementsTool', arguments: []),
            fn ($prompt, $attachments, $provider, $model) => 'summary placeholder',
        ]);

        $user = $this->createUserWithRole('transport', 'LOG');

        $response = $this->actingAs($user)
            ->withSession(['active_event_id' => $event->id])
            ->postJson('/ai/query', ['question' => 'which teams are delayed?']);

        $response->assertOk()->assertJson(['ok' => true]);

        OperationsCopilotAgent::assertPrompted(fn ($prompt) => $prompt->prompt === 'which teams are delayed?');
    }
}
