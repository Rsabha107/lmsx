<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Acting on a job now needs jobs.work instead of following from jobs.view, so a read-only
 * mobile_viewer role can see jobs without completing checkpoints. Every role and user that
 * could see jobs before keeps acting on them. Mirrors RolePermissionSeeder.
 */
return new class extends Migration
{
    private const VIEWER_PERMISSIONS = ['jobs.view', 'jobs.view-unassigned', 'jobs.view-all-functional-areas'];

    public function up(): void
    {
        $work = Permission::firstOrCreate(['name' => 'jobs.work', 'guard_name' => 'web']);
        $view = Permission::firstOrCreate(['name' => 'jobs.view', 'guard_name' => 'web']);

        Role::where('guard_name', 'web')
            ->where('name', '!=', 'mobile_viewer')
            ->where(fn ($q) => $q->where('name', 'admin')->orWhereHas('permissions', fn ($p) => $p->whereKey($view->id)))
            ->get()
            ->each(fn (Role $role) => $role->givePermissionTo($work));

        // Users holding jobs.view directly, not through a role.
        $direct = DB::table('model_has_permissions')->where('permission_id', $view->id)->get(['model_type', 'model_id']);
        foreach ($direct as $row) {
            DB::table('model_has_permissions')->insertOrIgnore([
                'permission_id' => $work->id,
                'model_type' => $row->model_type,
                'model_id' => $row->model_id,
            ]);
        }

        foreach (self::VIEWER_PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Role::firstOrCreate(['name' => 'mobile_viewer', 'guard_name' => 'web'])
            ->syncPermissions(self::VIEWER_PERMISSIONS);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'mobile_viewer')->where('guard_name', 'web')->delete();
        Permission::where('name', 'jobs.work')->where('guard_name', 'web')->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
