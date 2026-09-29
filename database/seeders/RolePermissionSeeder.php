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
    private const ROLES = ['admin', 'ground_control', 'transport', 'team_services', 'venue_ops', 'agency'];

    private const PERMISSIONS = [
        'movements.view',
        'movements.view-all-functional-areas',
        'jobs.view',
        'jobs.view-all-functional-areas',
        // In the mobile app, see and work jobs supervised by someone else.
        'jobs.view-unassigned',
        // Forging a completion record is an oversight action, not a field one.
        'jobs.override',
        // Reach events the user is not assigned to.
        'events.access-all',
        'events.view',
        'events.manage',
        'fleet.view',
        'fleet.manage',
        'plans.view',
        'plans.manage',
        // Set a movement's vehicle, driver and supervisor, and nothing else on it.
        'movements.assign-crew',
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
        'jobs.view-unassigned',
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

        // The outside crew agency: sees everything, changes only crew. No
        // jobs.view, because the job policy treats it as a grant to act on jobs.
        // RestrictAgencyToCrewAssignment closes the write routes guarded only by a view permission.
        Role::findByName('agency')->syncPermissions([
            'movements.view',
            'movements.view-all-functional-areas',
            'events.view',
            'fleet.view',
            'plans.view',
            'console.view',
            'movements.assign-crew',
        ]);
    }
}
