<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * The agency's fleet grant now also covers providers, so the name no longer fits.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::where('name', 'fleet.manage-vehicles-drivers')->where('guard_name', 'web')
            ->update(['name' => 'fleet.manage-resources']);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'fleet.manage-resources')->where('guard_name', 'web')
            ->update(['name' => 'fleet.manage-vehicles-drivers']);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
