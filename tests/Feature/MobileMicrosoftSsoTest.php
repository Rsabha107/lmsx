<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class MobileMicrosoftSsoTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFIER = 'verifier-0123456789-abcdefghijklmnopqrstuvwxyz';

    private const STATE = 'state-0123456789abcdef';

    private function challenge(string $verifier = self::VERIFIER): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    private function microsoftReturns(string $email): void
    {
        $azureUser = (new SocialiteUser)->map(['id' => 'entra-1', 'email' => $email, 'name' => 'Entra Name']);
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn(Mockery::mock(['user' => $azureUser]));
    }

    private function returnFromMicrosoft(): string
    {
        $response = $this->withSession(['mobile_sso' => ['challenge' => $this->challenge(), 'state' => self::STATE]])
            ->get('/auth/microsoft/callback');
        $response->assertRedirect();

        return $response->headers->get('Location');
    }

    public function test_the_start_refuses_a_malformed_challenge(): void
    {
        $this->get('/auth/microsoft/mobile?challenge=short&state='.self::STATE)->assertStatus(400);
    }

    public function test_the_app_gets_a_one_time_code_that_only_its_verifier_redeems(): void
    {
        $user = User::factory()->create(['email' => 'sup@example.com']);
        $this->microsoftReturns('sup@example.com');

        $location = $this->returnFromMicrosoft();
        $this->assertStringStartsWith('naqlalms://sso?', $location);
        $this->assertGuest('web');
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(self::STATE, $query['state']);

        $this->postJson('/api/mobile/sso/microsoft', ['code' => $query['code'], 'verifier' => self::VERIFIER])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['token']);

        $this->postJson('/api/mobile/sso/microsoft', ['code' => $query['code'], 'verifier' => self::VERIFIER])
            ->assertUnprocessable();
    }

    public function test_a_wrong_verifier_gets_nothing(): void
    {
        User::factory()->create(['email' => 'sup@example.com']);
        $this->microsoftReturns('sup@example.com');

        parse_str(parse_url($this->returnFromMicrosoft(), PHP_URL_QUERY), $query);

        $this->postJson('/api/mobile/sso/microsoft', ['code' => $query['code'], 'verifier' => str_repeat('x', 43)])
            ->assertUnprocessable();
    }

    public function test_an_unknown_microsoft_account_is_sent_back_with_an_error(): void
    {
        $this->microsoftReturns('stranger@example.com');

        parse_str(parse_url($this->returnFromMicrosoft(), PHP_URL_QUERY), $query);

        $this->assertArrayNotHasKey('code', $query);
        $this->assertStringContainsString('not authorised', $query['error']);
    }
}
