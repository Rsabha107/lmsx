<?php

namespace Tests\Feature;

use App\Ai\Agents\ConnectionCheckAgent;
use App\Ai\Agents\FlightScheduleLookupAgent;
use App\Ai\Agents\OperationsCopilotAgent;
use App\Support\AiProvider;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Setups → Settings → AI provider: pick Azure OpenAI or Anthropic for the assistant and AI imports. */
class AiProviderSettingTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        config([
            'ai.default' => 'anthropic',
            'ai.providers.anthropic.key' => 'test-anthropic',
            'ai.providers.azure.key' => 'test-azure',
            'ai.providers.azure.url' => 'https://example-sc.openai.azure.com/',
            'ai.providers.azure.deployment' => 'gpt-4o',
            'services.anthropic.model' => 'claude-test',
        ]);
    }

    public function test_an_admin_switches_the_assistant_to_azure_and_back(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)->get('/setups/settings')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('ai.current', 'anthropic')
                ->where('ai.options.0.value', 'azure')
                ->where('ai.options.0.configured', true)
                ->where('ai.options.0.endpoint', 'example-sc.openai.azure.com'));

        $this->actingAs($admin)->post('/setups/settings/ai-provider', ['provider' => 'azure'])->assertSessionHas('success');
        $this->assertSame('azure', AiProvider::current());
        $this->assertDatabaseHas('audit_logs', ['action' => 'AI provider changed']);

        $copilot = new OperationsCopilotAgent;
        $this->assertSame('azure', $copilot->provider());
        $this->assertNull($copilot->model(), 'Azure uses its deployment, not AI_MODEL');
        // The flight lookup needs web search, which only Anthropic offers here.
        $this->assertSame('anthropic', (new FlightScheduleLookupAgent)->provider());
        $this->assertSame('claude-test', (new FlightScheduleLookupAgent)->model());

        $this->actingAs($admin)->post('/setups/settings/ai-provider', ['provider' => 'anthropic']);
        $this->assertSame('anthropic', (new OperationsCopilotAgent)->provider());
        $this->assertSame('claude-test', (new OperationsCopilotAgent)->model());
    }

    public function test_a_provider_without_keys_cannot_be_picked(): void
    {
        config(['ai.providers.azure.key' => null]);

        $this->actingAs($this->createUserWithRole('admin'))
            ->post('/setups/settings/ai-provider', ['provider' => 'azure'])
            ->assertSessionHas('error');

        $this->assertSame('anthropic', AiProvider::current());
    }

    public function test_the_connection_test_reports_the_answer_or_the_error(): void
    {
        $admin = $this->createUserWithRole('admin');
        ConnectionCheckAgent::fake(['OK']);

        $this->actingAs($admin)->postJson('/setups/settings/ai-provider/test', ['provider' => 'azure'])
            ->assertOk()->assertJson(['ok' => true]);
        ConnectionCheckAgent::assertPrompted(fn ($prompt) => $prompt->provider()->name() === 'azure');

        config(['ai.providers.azure.url' => null]);
        $this->actingAs($admin)->postJson('/setups/settings/ai-provider/test', ['provider' => 'azure'])
            ->assertOk()->assertJson(['ok' => false]);
    }

    public function test_only_admins_change_or_test_the_provider(): void
    {
        $transport = $this->createUserWithRole('transport');

        $this->actingAs($transport)->post('/setups/settings/ai-provider', ['provider' => 'azure'])->assertForbidden();
        $this->actingAs($transport)->postJson('/setups/settings/ai-provider/test', ['provider' => 'azure'])->assertForbidden();
        $this->assertSame('anthropic', AiProvider::current());
    }
}
