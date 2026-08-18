<?php

namespace Tests\Feature;

use App\Ai\Agents\OperationsCopilotAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class AiCopilotRateLimitTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_requests_beyond_the_per_minute_limit_are_throttled(): void
    {
        OperationsCopilotAgent::fake(['ok']);

        $event = $this->createEvent();
        $user = $this->createUserWithRole('admin');

        $client = $this->actingAs($user)->withSession(['active_event_id' => $event->id]);

        // The limiter is registered for 10/minute per user (AppServiceProvider).
        for ($i = 0; $i < 10; $i++) {
            $client->postJson('/ai/query', ['question' => "question {$i}"])->assertOk();
        }

        $client->postJson('/ai/query', ['question' => 'one too many'])
            ->assertStatus(429);
    }
}
