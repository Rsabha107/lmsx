<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\JobIssue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_forwarded_headers_from_a_public_address_are_not_trusted(): void
    {
        $headers = ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.example'];

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->get('/login', $headers)->assertOk();
        $this->assertFalse(request()->isSecure());
        $this->assertNotSame('evil.example', request()->getHost());

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])->get('/login', $headers)->assertOk();
        $this->assertTrue(request()->isSecure());
    }

    public function test_mobile_uploads_are_rate_limited_per_user(): void
    {
        $event = $this->createEvent();
        Permission::firstOrCreate(['name' => 'jobs.view', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'jobs.view-all-functional-areas', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'ground_control', 'guard_name' => 'web'])->syncPermissions(['jobs.view', 'jobs.view-all-functional-areas']);

        $me = User::factory()->create();
        $me->assignRole('ground_control');
        $me->events()->attach($event->id);

        $team = $this->createTeam($event);
        $job = $this->createJob($event, $this->createMovement($event, $this->createPlan($event), $team), $team, ['supervisor_id' => $me->id]);
        $type = array_key_first(JobIssue::TYPES);

        RateLimiter::clear('mobile-upload');
        Sanctum::actingAs($me);

        for ($i = 1; $i <= 30; $i++) {
            $this->postJson("/api/mobile/jobs/{$job->id}/issues?event_id={$event->id}", ['type' => $type], ['Idempotency-Key' => "limit-test-{$i}-abcdef"])->assertSuccessful();
        }

        $this->postJson("/api/mobile/jobs/{$job->id}/issues?event_id={$event->id}", ['type' => $type], ['Idempotency-Key' => 'limit-test-31-abcdef'])
            ->assertStatus(429);
    }

    public function test_the_events_page_only_lists_events_the_user_may_reach(): void
    {
        foreach (['events.view'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'viewer', 'guard_name' => 'web'])->syncPermissions(['events.view']);

        $mine = $this->createEvent();
        $other = $this->createEvent();

        $user = User::factory()->create();
        $user->assignRole('viewer');
        $user->events()->attach($mine->id);

        $this->actingAs($user)->get('/events')->assertOk()->assertInertia(fn ($page) => $page
            ->where('events', fn ($events) => collect($events)->pluck('id')->all() === [$mine->id])
            ->where('eventList', fn ($list) => collect($list)->pluck('id')->all() === [$mine->id]));

        $this->actingAs($this->createUserWithRole('admin'))->get('/events')->assertOk()->assertInertia(fn ($page) => $page
            ->where('events', fn ($events) => collect($events)->pluck('id')->sort()->values()->all() === collect([$mine->id, $other->id])->sort()->values()->all()));
    }

    public function test_privileged_changes_are_audited_without_secrets(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $actor = User::factory()->create();
        $actor->assignRole('admin');
        $actor->assignRole('SecurityRole');
        $this->actingAs($actor);

        $this->post('/setups/roles', ['name' => 'auditor', 'permissions' => []])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'Role created', 'target' => 'auditor', 'user_id' => $actor->id]);

        $this->post('/setups/users', ['name' => 'New Person', 'email' => 'np@example.test', 'password' => 'super-secret-1', 'roles' => []])->assertRedirect();

        $entry = AuditLog::where('action', 'User created')->firstOrFail();
        $this->assertStringNotContainsString('super-secret-1', (string) $entry->meta);
        $this->assertStringNotContainsString('password', (string) $entry->meta);
        $this->assertSame('np@example.test', $entry->target);

        $created = User::where('email', 'np@example.test')->firstOrFail();
        $this->put("/setups/users/{$created->id}", ['name' => 'New Person', 'email' => 'np@example.test', 'password' => 'another-secret-2', 'roles' => []])->assertRedirect();

        $update = AuditLog::where('action', 'User updated')->firstOrFail();
        $this->assertStringContainsString('"password_changed":true', (string) $update->meta);
        $this->assertStringNotContainsString('another-secret-2', (string) $update->meta);

        $this->post('/fleet/providers', ['code' => 'AUD', 'name' => 'Audit Co', 'status' => 'active'])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'Provider created', 'target' => 'AUD']);
    }

    public function test_the_production_check_flags_an_unsafe_environment(): void
    {
        $this->artisan('app:check-production')->assertFailed();
    }
}
