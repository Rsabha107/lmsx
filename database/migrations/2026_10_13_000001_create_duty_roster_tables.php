<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Staff duty roster: shift types, the staff on an event's roster and their shift per day.
 * roster.manage mirrors RolePermissionSeeder (admin + SecurityRole).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shift_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('code', 4);
            $table->string('color', 7);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_leave')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('roster_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('designation', 100)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('roster_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_type_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'user_id', 'date']);
            $table->index(['event_id', 'date']);
        });

        $now = now();
        DB::table('shift_types')->insert([
            ['name' => 'Morning', 'code' => 'M', 'color' => '#16A34A', 'start_time' => '09:00', 'end_time' => '17:00', 'is_leave' => false, 'sort' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Evening', 'code' => 'E', 'color' => '#EA580C', 'start_time' => '12:00', 'end_time' => '21:00', 'is_leave' => false, 'sort' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Night', 'code' => 'N', 'color' => '#64748B', 'start_time' => '22:00', 'end_time' => '06:00', 'is_leave' => false, 'sort' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'General', 'code' => 'G', 'color' => '#7C3AED', 'start_time' => null, 'end_time' => null, 'is_leave' => false, 'sort' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Leave', 'code' => 'L', 'color' => '#EAB308', 'start_time' => null, 'end_time' => null, 'is_leave' => true, 'sort' => 5, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $permission = Permission::firstOrCreate(['name' => 'roster.manage', 'guard_name' => 'web']);
        foreach (['admin', 'SecurityRole'] as $role) {
            Role::where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_shifts');
        Schema::dropIfExists('roster_members');
        Schema::dropIfExists('shift_types');

        Permission::where('name', 'roster.manage')->where('guard_name', 'web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
