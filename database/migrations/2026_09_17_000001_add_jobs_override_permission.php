<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Overriding a checkpoint writes a completion the supervisor never witnessed,
 * so it moves from "anyone who can see the job" to an explicit grant. Seeded
 * here as well as in RolePermissionSeeder so existing databases pick it up
 * without a full reseed.
 */
return new class extends Migration
{
    private const OVERSIGHT_ROLES = ['admin', 'ground_control'];

    public function up(): void
    {
        $permission = Permission::firstOrCreate([
            'name' => 'jobs.override',
            'guard_name' => 'web',
        ]);

        foreach (self::OVERSIGHT_ROLES as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            $role?->givePermissionTo($permission);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'jobs.override')->where('guard_name', 'web')->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
