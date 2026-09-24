<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesOperationsFixtures;
use Tests\TestCase;

class AccessPageTest extends TestCase
{
    use RefreshDatabase, CreatesOperationsFixtures;

    public function test_non_admins_cannot_reach_the_access_page(): void
    {
        $this->get('/setups/access')->assertRedirect('/login');

        $this->actingAs(\App\Models\User::factory()->create())
            ->get('/setups/access')
            ->assertForbidden();
    }

    public function test_admin_sees_roles_and_permissions_on_one_page(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)->get('/setups/access')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Setups/Access')
                ->has('roles')
                ->has('permissions'));
    }

    public function test_the_old_separate_pages_redirect_to_the_combined_one(): void
    {
        $admin = $this->createUserWithRole('admin');

        $this->actingAs($admin)->get('/setups/roles')->assertRedirect('/setups/access');
        $this->actingAs($admin)->get('/setups/permissions')->assertRedirect('/setups/access');
    }
}
