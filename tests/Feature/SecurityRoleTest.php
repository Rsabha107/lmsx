<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SecurityRoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function userWith(string $role): User
    {
        return tap(User::factory()->create())->assignRole($role);
    }

    public function test_only_the_security_role_reaches_roles_and_permissions(): void
    {
        $this->actingAs($this->userWith('admin'))->get('/setups/access')->assertForbidden();
        $this->actingAs($this->userWith('SecurityRole'))->get('/setups/access')->assertOk();
    }

    public function test_the_menu_item_follows_the_permission(): void
    {
        $this->actingAs($this->userWith('admin'))->get('/setups/users')
            ->assertInertia(fn ($page) => $page->where('auth.can', fn ($can) => collect($can)->get('access.manage') === false));

        $this->actingAs($this->userWith('SecurityRole'))->get('/setups/access')
            ->assertInertia(fn ($page) => $page->where('auth.can', fn ($can) => collect($can)->get('access.manage') === true));
    }

    public function test_built_in_roles_cannot_be_deleted_or_renamed(): void
    {
        $security = $this->actingAs($this->userWith('SecurityRole'));

        foreach (['admin', 'SecurityRole'] as $name) {
            $role = Role::findByName($name);

            $security->delete("/setups/roles/{$role->id}")->assertForbidden();
            $security->put("/setups/roles/{$role->id}", ['name' => 'Renamed'])->assertSessionHasErrors('name');

            $this->assertSame($name, $role->refresh()->name);
        }
    }

    public function test_other_roles_can_still_be_deleted(): void
    {
        $role = Role::create(['name' => 'temp', 'guard_name' => 'web']);

        $this->actingAs($this->userWith('SecurityRole'))->delete("/setups/roles/{$role->id}")->assertRedirect();

        $this->assertModelMissing($role);
    }

    public function test_an_admin_cannot_hand_out_the_security_role(): void
    {
        $admin = $this->userWith('admin');
        $target = User::factory()->create();
        $securityId = Role::findByName('SecurityRole')->id;

        $this->actingAs($admin)
            ->put("/setups/users/{$target->id}", ['name' => $target->name, 'email' => $target->email, 'roles' => [$securityId]])
            ->assertForbidden();

        $this->assertFalse($target->refresh()->hasRole('SecurityRole'));
    }

    public function test_an_admin_editing_a_security_holder_keeps_the_role(): void
    {
        $holder = $this->userWith('SecurityRole');
        $adminId = Role::findByName('admin')->id;
        $securityId = Role::findByName('SecurityRole')->id;

        $this->actingAs($this->userWith('admin'))
            ->put("/setups/users/{$holder->id}", ['name' => 'Renamed', 'email' => $holder->email, 'roles' => [$securityId, $adminId]])
            ->assertRedirect();

        $this->assertTrue($holder->refresh()->hasRole('SecurityRole'));
        $this->assertSame('Renamed', $holder->name);
    }

    public function test_an_admin_cannot_remove_the_security_role_or_delete_its_holder(): void
    {
        $holder = $this->userWith('SecurityRole');
        $admin = $this->userWith('admin');

        $this->actingAs($admin)
            ->put("/setups/users/{$holder->id}", ['name' => $holder->name, 'email' => $holder->email, 'roles' => []])
            ->assertForbidden();
        $this->actingAs($admin)->delete("/setups/users/{$holder->id}")->assertForbidden();

        $this->assertTrue($holder->refresh()->hasRole('SecurityRole'));
    }

    public function test_an_admin_who_also_holds_the_security_role_can_grant_it(): void
    {
        $actor = $this->userWith('admin');
        $actor->assignRole('SecurityRole');
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->put("/setups/users/{$target->id}", ['name' => $target->name, 'email' => $target->email, 'roles' => [Role::findByName('SecurityRole')->id]])
            ->assertRedirect();

        $this->assertTrue($target->refresh()->hasRole('SecurityRole'));
    }
}
