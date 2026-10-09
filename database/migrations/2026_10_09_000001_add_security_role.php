<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SecurityRole alone holds access.manage, which gates Roles & Permissions. Admin does not get it.
 * Mirrors RolePermissionSeeder.
 */
return new class extends Migration
{
    private const OWNER_EMAIL = 'r.sabha@sc.qa';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'access.manage', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'SecurityRole', 'guard_name' => 'web']);
        $role->syncPermissions(['access.manage']);

        Role::where('name', 'admin')->where('guard_name', 'web')->first()?->revokePermissionTo('access.manage');

        User::where('email', self::OWNER_EMAIL)->first()?->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'SecurityRole')->where('guard_name', 'web')->delete();
        Permission::where('name', 'access.manage')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
