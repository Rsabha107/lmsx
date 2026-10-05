<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Lets the crew agency maintain vehicles and drivers without full fleet.manage. Mirrors RolePermissionSeeder.
 */
return new class extends Migration
{
    private const PERMISSION = 'fleet.manage-vehicles-drivers';

    public function up(): void
    {
        Permission::firstOrCreate(['name' => self::PERMISSION, 'guard_name' => 'web']);

        foreach (['admin', 'agency'] as $role) {
            Role::where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo(self::PERMISSION);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
