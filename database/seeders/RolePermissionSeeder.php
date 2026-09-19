<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Roles matching the app's real functional-area structure (LOG/AND/MOB)
     * plus admin/oversight — not an aspirational role list with no basis in
     * this codebase's data model.
     */
    private const ROLES = ['admin', 'ground_control', 'transport', 'team_services', 'venue_ops'];

    private const PERMISSIONS = [
        'movements.view',
        'movements.view-all-functional-areas',
        'jobs.view',
        'jobs.view-all-functional-areas',
        // Forging a completion record is an oversight action, not a field one.
        'jobs.override',
        // Reach events the user is not assigned to.
        'events.access-all',
        // Flip the global active_flag the mobile app reads as its default event.
        'events.set-mobile-default',
        'events.view',
        'events.manage',
        'fleet.view',
        'fleet.manage',
        'plans.view',
        'plans.manage',
        'analytics.view',
        'audit.view',
        // The desktop web console: dashboard, schedule, jobs queue, tracker, etc.
        'console.view',
        'ai.use',
    ];

    /** Read-only access every desk-based operational role needs. */
    private const SCOPED_PERMISSIONS = [
        'movements.view',
        'jobs.view',
        'events.view',
        'fleet.view',
        'plans.view',
        'console.view',
        'ai.use',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        Role::findByName('admin')->syncPermissions(self::PERMISSIONS);

        // Field supervisors: the mobile job workflow and nothing else. No
        // console.view, so every desk page is closed to them, and no
        // events.access-all, so their event assignments bind.
        Role::findByName('ground_control')->syncPermissions([
            'jobs.view',
            'jobs.view-all-functional-areas',
        ]);

        // transport/team_services/venue_ops are scoped to their functional
        // area via the user_functional_areas pivot table, not by permission
        // name — they share the same base (non-"-all-") permissions.
        foreach (['transport', 'team_services', 'venue_ops'] as $scopedRole) {
            Role::findByName($scopedRole)->syncPermissions(self::SCOPED_PERMISSIONS);
        }
    }
}
