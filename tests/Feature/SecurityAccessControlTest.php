<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class SecurityAccessControlTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_event_manager_cannot_write_an_unassigned_event(): void
    {
        Permission::firstOrCreate(['name' => 'events.manage', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'event_manager', 'guard_name' => 'web'])->syncPermissions(['events.manage']);

        $mine = $this->createEvent();
        $other = $this->createEvent();
        $user = User::factory()->create();
        $user->assignRole('event_manager');
        $user->events()->attach($mine->id);

        $this->actingAs($user)->post("/events/{$other->id}/teams", [
            'code' => 'DENY',
            'team_name' => 'Unauthorized team',
        ])->assertForbidden();

        $this->assertDatabaseMissing('teams', ['event_id' => $other->id, 'code' => 'DENY']);
    }

    public function test_console_viewer_cannot_change_global_venues(): void
    {
        Permission::firstOrCreate(['name' => 'console.view', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'console_viewer', 'guard_name' => 'web'])->syncPermissions(['console.view']);
        $user = User::factory()->create();
        $user->assignRole('console_viewer');

        $this->actingAs($user)->post('/venues', [
            'name' => 'Unauthorized venue',
            'type' => 'stadium',
        ])->assertForbidden();

        $this->assertDatabaseMissing('venues', ['name' => 'Unauthorized venue']);
    }

    public function test_web_login_is_limited_by_account_and_network(): void
    {
        RateLimiter::clear('login:ip:127.0.0.1');
        RateLimiter::clear('login:email:person@example.test');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', ['email' => 'person@example.test', 'password' => 'invalid-password'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'person@example.test', 'password' => 'invalid-password'])
            ->assertStatus(429);
    }

    public function test_resource_schedule_does_not_resolve_an_arbitrary_user_as_supervisor(): void
    {
        Permission::firstOrCreate(['name' => 'movements.view', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'movement_viewer', 'guard_name' => 'web'])->syncPermissions(['movements.view']);
        $viewer = User::factory()->create();
        $viewer->assignRole('movement_viewer');
        $otherUser = User::factory()->create();

        $this->actingAs($viewer)->get("/resource-schedule?type=supervisor&id={$otherUser->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('resource', null));
    }
}
