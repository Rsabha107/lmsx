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

        Role::findByName('ground_control')->syncPermissions([
            'movements.view',
            'movements.view-all-functional-areas',
            'jobs.view',
            'jobs.view-all-functional-areas',
            'ai.use',
        ]);

        // transport/team_services/venue_ops are scoped to their functional
        // area via the user_functional_areas pivot table, not by permission
        // name — they share the same base (non-"-all-") permissions.
        foreach (['transport', 'team_services', 'venue_ops'] as $scopedRole) {
            Role::findByName($scopedRole)->syncPermissions([
                'movements.view',
                'jobs.view',
                'ai.use',
            ]);
        }
    }
}
