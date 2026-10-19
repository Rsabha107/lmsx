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
    private const ROLES = ['admin', 'ground_control', 'transport', 'team_services', 'venue_ops', 'agency', 'SecurityRole', 'mobile_viewer'];

    private const PERMISSIONS = [
        'movements.view',
        'movements.view-all-functional-areas',
        'jobs.view',
        'jobs.view-all-functional-areas',
        // In the mobile app, see and work jobs supervised by someone else.
        'jobs.view-unassigned',
        // Act on a job it can see: complete checkpoints, report and resolve issues, change its status.
        'jobs.work',
        // Forging a completion record is an oversight action, not a field one.
        'jobs.override',
        // Reach events the user is not assigned to.
        'events.access-all',
        'events.view',
        'events.manage',
        'fleet.view',
        'fleet.manage',
        // Add, edit and delete vehicles, drivers and providers only (no other master data).
        'fleet.manage-resources',
        'plans.view',
        'plans.manage',
        // Set a movement's vehicle, driver and supervisor, and nothing else on it.
        'movements.assign-crew',
        'analytics.view',
        'audit.view',
        // The desktop web console: dashboard, schedule, jobs queue, tracker, etc.
        'console.view',
        'ai.use',
        // Open and change Roles & Permissions. Held only by SecurityRole, never by admin.
        'access.manage',
        // Open and edit the staff duty roster and its shift types. Admin only.
        'roster.manage',
    ];

    /** Read-only access every desk-based operational role needs. */
    private const SCOPED_PERMISSIONS = [
        'movements.view',
        'jobs.view',
        'jobs.view-unassigned',
        'jobs.work',
        'events.view',
        'fleet.view',
        'plans.view',
        'console.view',
        'ai.use',
    ];

    private const MOBILE_VIEWER_PERMISSIONS = [
        'jobs.view',
        'jobs.view-unassigned',
        'jobs.view-all-functional-areas',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        Role::findByName('admin')->syncPermissions(array_diff(self::PERMISSIONS, ['access.manage']));
        Role::findByName('SecurityRole')->syncPermissions(['access.manage']);

        // Field supervisors: the mobile job workflow and nothing else. No
        // console.view, so every desk page is closed to them, and no
        // events.access-all, so their event assignments bind.
        Role::findByName('ground_control')->syncPermissions([
            'jobs.view',
            'jobs.view-all-functional-areas',
            'jobs.work',
        ]);

        // Read-only mobile app users: every job of their events (their provider's only, when linked
        // to one), with no jobs.work, so they follow checkpoint progress but never act on it.
        Role::findByName('mobile_viewer')->syncPermissions(self::MOBILE_VIEWER_PERMISSIONS);

        // transport/team_services/venue_ops are scoped to their functional
        // area via the user_functional_areas pivot table, not by permission
        // name — they share the same base (non-"-all-") permissions.
        foreach (['transport', 'team_services', 'venue_ops'] as $scopedRole) {
            Role::findByName($scopedRole)->syncPermissions(self::SCOPED_PERMISSIONS);
        }

        // The outside crew agency: sees everything, changes only crew, vehicles, drivers and providers. No
        // jobs.view: it reads jobs only through the Day Board (JobOperationPolicy::read).
        // RestrictAgencyToCrewAssignment closes the write routes guarded only by a view permission.
        Role::findByName('agency')->syncPermissions([
            'movements.view',
            'movements.view-all-functional-areas',
            'events.view',
            'fleet.view',
            'plans.view',
            'console.view',
            'movements.assign-crew',
            'fleet.manage-resources',
        ]);
    }
}
