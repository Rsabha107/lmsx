<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** The duty roster is admin only. Mirrors RolePermissionSeeder. */
return new class extends Migration
{
    public function up(): void
    {
        Role::where('name', 'SecurityRole')->where('guard_name', 'web')->first()?->revokePermissionTo('roster.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Role::where('name', 'SecurityRole')->where('guard_name', 'web')->first()?->givePermissionTo('roster.manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
