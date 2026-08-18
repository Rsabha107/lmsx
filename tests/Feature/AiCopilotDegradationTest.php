<?php

namespace Tests\Feature;

use App\Ai\Agents\OperationsCopilotAgent;
use App\Models\AiInteraction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class AiCopilotDegradationTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_provider_failure_returns_a_clean_response_not_a_500(): void
    {
        OperationsCopilotAgent::fake(function () {
            throw new RuntimeException('simulated provider outage');
        });

        $event = $this->createEvent();
        $user = $this->createUserWithRole('admin');

        $response = $this->actingAs($user)
            ->withSession(['active_event_id' => $event->id])
            ->postJson('/ai/query', ['question' => 'is anything delayed?']);

        $response->assertOk()->assertJson([
            'ok' => false,
            'message' => 'The AI assistant is temporarily unavailable. Please try again shortly.',
        ]);

        $this->assertDatabaseHas('ai_interactions', [
            'user_id' => $user->id,
            'status' => 'failed',
        ]);

        $logged = AiInteraction::first();
        $this->assertStringContainsString('simulated provider outage', $logged->error_message);
    }

    public function test_provider_failure_does_not_affect_unrelated_routes(): void
    {
        OperationsCopilotAgent::fake(function () {
            throw new RuntimeException('simulated provider outage');
        });

        $user = $this->createUserWithRole('admin');

        // Any other authenticated route should be completely unaffected by
        // the AI provider being down — the app must keep working without AI.
        $this->actingAs($user)->get('/schedule')->assertOk();
    }
}
