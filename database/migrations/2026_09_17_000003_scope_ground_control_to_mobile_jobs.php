<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Splits the desktop web console off behind its own permission and reduces
 * ground_control to the mobile job workflow only.
 */
return new class extends Migration
{
    private const CONSOLE_ROLES = ['admin', 'transport', 'team_services', 'venue_ops'];

    private const GROUND_CONTROL = [
        'jobs.view',
        'jobs.view-all-functional-areas',
    ];

    public function up(): void
    {
        $console = Permission::firstOrCreate(['name' => 'console.view', 'guard_name' => 'web']);

        foreach (self::CONSOLE_ROLES as $roleName) {
            Role::where('name', $roleName)->where('guard_name', 'web')->first()?->givePermissionTo($console);
        }

        Role::where('name', 'ground_control')->where('guard_name', 'web')
            ->first()?->syncPermissions(self::GROUND_CONTROL);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'console.view')->where('guard_name', 'web')->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
