<?php

namespace Tests\Feature;

use App\Mail\AccessGrantedMail;
use App\Mail\UserInvitationMail;
use App\Models\Event;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\UserAccessService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

/** Active/inactive users, emailed invitations and SC/LOC access grants (Setups → Users). */
class UserAccessTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    private User $admin;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = $this->createUserWithRole('admin');
        $this->event = $this->createEvent();
        Mail::fake();
    }

    private function role(string $name): int
    {
        return Role::where('name', $name)->value('id');
    }

    private function user(string $role = 'transport', array $attrs = []): User
    {
        $user = User::factory()->create($attrs + ['password' => Hash::make('secret-pass')]);
        $user->assignRole($role);
        $user->events()->attach($this->event->id);

        return $user;
    }

    private function invite(array $data = []): string
    {
        $this->actingAs($this->admin)->post('/setups/users/invitations', $data + [
            'email' => 'new.driver@gwc.example', 'name' => 'New Driver',
            'role_ids' => [$this->role('ground_control')], 'event_ids' => [$this->event->id],
        ])->assertSessionHasNoErrors();

        $link = null;
        Mail::assertSent(UserInvitationMail::class, function (UserInvitationMail $mail) use (&$link) {
            $link = $mail->link;

            return true;
        });
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $link;
    }

    public function test_a_deactivated_user_cannot_sign_in_and_their_session_and_tokens_end(): void
    {
        $user = $this->user();
        $user->createToken('phone');

        $this->actingAs($this->admin)->post("/setups/users/{$user->id}/status", ['active' => false])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertNotNull($user->deactivated_at);
        $this->assertSame(0, $user->tokens()->count());

        // An open session is ended on its next request.
        $this->actingAs($user)->get('/')->assertRedirect('/login');
        $this->assertGuest();

        // Password sign-in, web and mobile.
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertSessionHasErrors(['email' => User::INACTIVE_MESSAGE]);
        $this->assertGuest();
        $this->postJson('/api/mobile/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertJsonValidationErrors('email');

        // Reactivated: signs in again and the time is recorded.
        $this->actingAs($this->admin)->post("/setups/users/{$user->id}/status", ['active' => true]);
        auth()->logout();
        $this->post('/login', ['email' => $user->email, 'password' => 'secret-pass'])->assertRedirect();
        $this->assertAuthenticatedAs($user->refresh());
        $this->assertNotNull($user->last_login_at);
    }

    public function test_a_deactivated_supervisor_is_not_offered_as_crew(): void
    {
        $active = $this->user('ground_control');
        $inactive = $this->user('ground_control', ['is_active' => false]);

        $ids = User::fieldSupervisors($this->admin)->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_admins_cannot_deactivate_themselves_and_only_admins_may_change_status(): void
    {
        $this->actingAs($this->admin)->post("/setups/users/{$this->admin->id}/status", ['active' => false])->assertForbidden();
        $this->actingAs($this->user())->post("/setups/users/{$this->admin->id}/status", ['active' => false])->assertForbidden();
        $this->assertTrue($this->admin->refresh()->is_active);
    }

    public function test_an_invitation_creates_the_account_through_a_short_signed_link(): void
    {
        $link = $this->invite();

        $this->assertMatchesRegularExpression('#/i/[A-Za-z0-9]{12}$#', $link);
        $this->assertSame(1, UserInvitation::pending()->count());
        $this->assertNotSame(substr($link, -12), UserInvitation::first()->code_hash);

        // The short link resolves to a signed page; tampering with it fails.
        $signed = $this->get(parse_url($link, PHP_URL_PATH))->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('signature=', $signed);
        $this->get($signed)->assertOk()->assertInertia(fn ($page) => $page->component('Auth/AcceptInvitation')->where('state', 'open')
            ->where('invitation.email', 'new.driver@gwc.example'));
        $this->get(str_replace('code=', 'code=X', $signed))->assertForbidden();

        $submit = str_replace('/invitation/accept?', '/invitation/accept?', $signed);
        $this->post($submit, ['name' => 'New Driver', 'password' => 'long-enough-1', 'password_confirmation' => 'long-enough-1'])
            ->assertRedirect(route('dashboard'));

        $user = User::where('email', 'new.driver@gwc.example')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('ground_control'));
        $this->assertSame([$this->event->id], $user->events->pluck('id')->all());
        $this->assertSame('accepted', UserInvitation::first()->status());

        // Used once only.
        auth()->logout();
        $this->get(parse_url($link, PHP_URL_PATH))->assertInertia(fn ($page) => $page->where('state', 'accepted'));
    }

    public function test_resending_kills_the_old_link_and_revoking_closes_it(): void
    {
        $old = $this->invite();
        $invitation = UserInvitation::first();

        $this->actingAs($this->admin)->post("/setups/users/invitations/{$invitation->id}/resend")->assertSessionHasNoErrors();
        auth()->logout();
        $this->get(parse_url($old, PHP_URL_PATH))->assertInertia(fn ($page) => $page->where('state', 'invalid'));
        $this->assertSame(2, $invitation->refresh()->sent_count);

        $this->actingAs($this->admin)->delete("/setups/users/invitations/{$invitation->id}");
        $this->assertSame('revoked', $invitation->refresh()->status());
    }

    public function test_invitations_refuse_existing_users_staff_addresses_and_missing_events(): void
    {
        $as = $this->actingAs($this->admin);
        $base = ['role_ids' => [$this->role('transport')], 'event_ids' => [$this->event->id]];

        $as->post('/setups/users/invitations', $base + ['email' => $this->admin->email])->assertSessionHasErrors('email');
        $as->post('/setups/users/invitations', $base + ['email' => 'someone@sc.qa'])->assertSessionHasErrors('email');
        $as->post('/setups/users/invitations', ['email' => 'x@gwc.example', 'role_ids' => [$this->role('transport')]])->assertSessionHasErrors('event_ids');

        // Real admins lack access.manage (the fixture admin has every permission).
        Role::findByName('admin')->revokePermissionTo(User::ACCESS_PERMISSION);
        $this->admin->refresh();
        $this->actingAs($this->admin)->post('/setups/users/invitations', ['email' => 'y@gwc.example', 'role_ids' => [$this->role(User::SECURITY_ROLE)]])->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_granting_access_creates_a_staff_account_or_adds_to_an_existing_one(): void
    {
        $other = $this->createEvent();
        $as = $this->actingAs($this->admin);

        $as->post('/setups/users/grant-access', [
            'email' => 'a.person@loc.qa', 'name' => 'A Person',
            'role_ids' => [$this->role('transport')], 'event_ids' => [$this->event->id],
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'a.person@loc.qa')->firstOrFail();
        $this->assertTrue($user->hasRole('transport'));
        Mail::assertSent(AccessGrantedMail::class, fn ($m) => $m->hasTo('a.person@loc.qa'));

        // Again, for another event and role, on a deactivated account: added, nothing removed, reactivated.
        app(UserAccessService::class)->setActive($user, false, $this->admin);
        $as->post('/setups/users/grant-access', [
            'email' => 'A.Person@loc.qa', 'role_ids' => [$this->role('venue_ops')], 'event_ids' => [$other->id], 'notify' => false,
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('transport') && $user->hasRole('venue_ops'));
        $this->assertEqualsCanonicalizing([$this->event->id, $other->id], $user->events->pluck('id')->all());
        $this->assertSame(1, User::where('email', 'a.person@loc.qa')->count());

        $as->post('/setups/users/grant-access', ['email' => 'out@gwc.example', 'name' => 'X', 'role_ids' => [$this->role('transport')], 'event_ids' => [$this->event->id]])
            ->assertSessionHasErrors('email');
    }

    public function test_a_temporary_password_must_be_replaced_before_anything_else(): void
    {
        $this->actingAs($this->admin)->post('/setups/users', [
            'name' => 'Temp Person', 'email' => 'temp@gwc.example', 'password' => 'temporary-1', 'must_change_password' => true,
            'roles' => [$this->role('transport')], 'events' => [$this->event->id],
        ])->assertSessionHasNoErrors();
        $user = User::where('email', 'temp@gwc.example')->firstOrFail();
        $this->assertTrue($user->must_change_password);

        auth()->logout();
        $this->post('/login', ['email' => 'temp@gwc.example', 'password' => 'temporary-1']);
        $this->get('/jobs')->assertRedirect('/password/change');
        $this->get('/password/change')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/ChangePassword'));

        $this->post('/password/change', ['password' => 'temporary-1', 'password_confirmation' => 'temporary-1'])->assertSessionHasErrors('password');
        $this->post('/password/change', ['password' => 'my-own-pass-1', 'password_confirmation' => 'my-own-pass-1'])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('my-own-pass-1', $user->password));
        $this->get('/password/change')->assertRedirect(route('dashboard'));
    }

    public function test_an_agency_user_can_replace_a_temporary_password(): void
    {
        $agency = $this->user('agency');
        $agency->forceFill(['password' => Hash::make('temporary-1'), 'must_change_password' => true])->save();

        $this->actingAs($agency)->withHeader('X-Inertia', 'true')
            ->post('/password/change', ['password' => 'my-own-pass-1', 'password_confirmation' => 'my-own-pass-1'])
            ->assertRedirect(route('dashboard'));

        $this->assertFalse($agency->refresh()->must_change_password);
        $this->assertTrue(Hash::check('my-own-pass-1', $agency->password));
    }

    public function test_the_edit_dialog_switches_a_user_off_like_the_status_action(): void
    {
        $user = $this->user();
        $user->createToken('phone');

        $this->actingAs($this->admin)->put("/setups/users/{$user->id}", [
            'name' => $user->name, 'email' => $user->email, 'roles' => [$this->role('transport')], 'events' => [$this->event->id], 'is_active' => false,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($user->refresh()->is_active);
        $this->assertSame(0, $user->tokens()->count());

        $this->actingAs($this->admin)->put("/setups/users/{$this->admin->id}", [
            'name' => $this->admin->name, 'email' => $this->admin->email, 'roles' => [$this->role('admin')], 'is_active' => false,
        ])->assertForbidden();
    }

    public function test_granting_only_a_role_to_staff_who_already_have_events_needs_no_new_event(): void
    {
        $staff = $this->user('transport', ['email' => 'has.events@sc.qa']);

        $this->actingAs($this->admin)->post('/setups/users/grant-access', [
            'email' => 'has.events@sc.qa', 'role_ids' => [$this->role('venue_ops')], 'event_ids' => [], 'notify' => false,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($staff->refresh()->hasRole('venue_ops'));
    }

    public function test_microsoft_sign_in_is_refused_for_a_deactivated_account(): void
    {
        $user = $this->user('transport', ['email' => 'staff@sc.qa', 'is_active' => false]);
        $azure = \Mockery::mock(\Laravel\Socialite\Two\User::class);
        $azure->shouldReceive('getEmail')->andReturn('staff@sc.qa');
        $provider = \Mockery::mock(\SocialiteProviders\Microsoft\Provider::class);
        $provider->shouldReceive('user')->andReturn($azure);
        $provider->shouldReceive('getLogoutUrl')->andReturn('https://login.example/logout');
        \Laravel\Socialite\Facades\Socialite::shouldReceive('driver')->with('microsoft')->andReturn($provider);

        $this->get('/auth/microsoft/callback')->assertRedirect('https://login.example/logout');
        $this->assertGuest();
        $this->assertSame(User::INACTIVE_MESSAGE, session('error'));
        $this->assertNull($user->refresh()->last_login_at);
    }
}
