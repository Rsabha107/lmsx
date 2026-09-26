<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * An event's status now follows its dates (upcoming / current / past), and
 * active_flag means "not cancelled" rather than "the mobile app's default event".
 */
return new class extends Migration
{
    public function up(): void
    {
        // The old flag was exclusive - set on one event, cleared on all others -
        // so its values would read as "every other event is cancelled".
        DB::table('events')->update(['active_flag' => true]);

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Permission::where('name', 'events.set-mobile-default')->where('guard_name', 'web')->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('status', 20)->default('upcoming')->after('end_date');
        });

        Permission::firstOrCreate(['name' => 'events.set-mobile-default', 'guard_name' => 'web']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
