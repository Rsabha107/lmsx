<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The mobile app now shows field supervisors only the jobs they supervise.
 * Desk roles keep seeing every job in their areas through this grant; it is
 * deliberately not given to ground_control. Mirrors RolePermissionSeeder.
 */
return new class extends Migration
{
    private const ROLES = ['admin', 'transport', 'team_services', 'venue_ops'];

    public function up(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'jobs.view-unassigned', 'guard_name' => 'web']);

        foreach (self::ROLES as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'jobs.view-unassigned')->where('guard_name', 'web')->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
