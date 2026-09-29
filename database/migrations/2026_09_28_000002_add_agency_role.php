<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The outside crew agency: reads the console, sets only a movement's vehicle,
 * driver and supervisor. Mirrors RolePermissionSeeder.
 */
return new class extends Migration
{
    private const AGENCY_PERMISSIONS = [
        'movements.view',
        'movements.view-all-functional-areas',
        'events.view',
        'fleet.view',
        'plans.view',
        'console.view',
        'movements.assign-crew',
    ];

    public function up(): void
    {
        foreach (self::AGENCY_PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'agency', 'guard_name' => 'web'])->syncPermissions(self::AGENCY_PERMISSIONS);
        Role::where('name', 'admin')->where('guard_name', 'web')->first()?->givePermissionTo('movements.assign-crew');

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'agency')->where('guard_name', 'web')->delete();
        Permission::where('name', 'movements.assign-crew')->where('guard_name', 'web')->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
