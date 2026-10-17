<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class SharedRolesTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_pages_receive_the_users_roles_from_the_server(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = $this->createProviderUser('agency');
        $event = $this->createEvent();
        $user->events()->attach($event);

        $this->actingAs($user)->withSession(['active_event_id' => $event->id])
            ->get('/plans')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.roles', ['agency']));
    }
}
