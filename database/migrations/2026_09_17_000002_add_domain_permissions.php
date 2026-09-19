<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Domain permissions for events, fleet, plans, analytics and audit, plus the two
 * event-scope grants that replace "no assignments means unrestricted".
 *
 * Existing databases pick these up here rather than via a full reseed.
 */
return new class extends Migration
{
    private const PERMISSIONS = [
        'events.access-all',
        'events.set-mobile-default',
        'events.view',
        'events.manage',
        'fleet.view',
        'fleet.manage',
        'plans.view',
        'plans.manage',
        'analytics.view',
        'audit.view',
    ];

    private const GRANTS = [
        'admin' => self::PERMISSIONS,
        'ground_control' => [
            'events.access-all',
            'events.view',
            'fleet.view',
            'fleet.manage',
            'plans.view',
            'plans.manage',
            'analytics.view',
            'audit.view',
        ],
        'transport' => ['events.view', 'fleet.view', 'plans.view'],
        'team_services' => ['events.view', 'fleet.view', 'plans.view'],
        'venue_ops' => ['events.view', 'fleet.view', 'plans.view'],
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::GRANTS as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            $role?->givePermissionTo($permissions);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISSIONS)->where('guard_name', 'web')->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
