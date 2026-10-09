<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // users.provider_id is the SSO id, hence the longer name here.
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('fleet_provider_id')->nullable()->index();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('fleet_provider_id')->nullable();
        });

        Schema::table('movements', function (Blueprint $table) {
            $table->unsignedBigInteger('fleet_provider_id')->nullable()->index();
        });

        Schema::create('event_driver', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'driver_id']);
        });

        Schema::create('event_vehicle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['event_id', 'vehicle_id']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('event_vehicle');
        Schema::dropIfExists('event_driver');

        Schema::table('movements', function (Blueprint $table) {
            $table->dropIndex(['fleet_provider_id']);
            $table->dropColumn('fleet_provider_id');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('fleet_provider_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['fleet_provider_id']);
            $table->dropColumn('fleet_provider_id');
        });
    }

    /** Keeps today's behaviour: every movement keeps its crew, and a lone provider owns everything. */
    private function backfill(): void
    {
        DB::statement('UPDATE movements m JOIN vehicles v ON v.id = m.vehicle_id SET m.fleet_provider_id = v.provider_id WHERE m.fleet_provider_id IS NULL AND v.provider_id IS NOT NULL');
        DB::statement('UPDATE movements m JOIN drivers d ON d.id = m.driver_id SET m.fleet_provider_id = d.provider_id WHERE m.fleet_provider_id IS NULL AND d.provider_id IS NOT NULL');

        $providers = DB::table('fleet_providers')->pluck('id');
        if ($providers->count() === 1) {
            $only = $providers->first();
            DB::table('movements')->whereNull('fleet_provider_id')->update(['fleet_provider_id' => $only]);
            DB::table('events')->whereNull('fleet_provider_id')->update(['fleet_provider_id' => $only]);

            $roleIds = DB::table('roles')->whereIn('name', ['agency', 'ground_control'])->pluck('id');
            if ($roleIds->isNotEmpty()) {
                DB::table('users')
                    ->whereIn('id', DB::table('model_has_roles')->whereIn('role_id', $roleIds)->where('model_type', 'App\\Models\\User')->select('model_id'))
                    ->update(['fleet_provider_id' => $only]);
            }
        }

        // Everyone already on a movement stays in that event's pool, plus the whole fleet of an event's provider.
        DB::statement('INSERT IGNORE INTO event_vehicle (event_id, vehicle_id, created_at, updated_at) SELECT DISTINCT event_id, vehicle_id, NOW(), NOW() FROM movements WHERE vehicle_id IS NOT NULL AND event_id IS NOT NULL');
        DB::statement('INSERT IGNORE INTO event_vehicle (event_id, vehicle_id, created_at, updated_at) SELECT DISTINCT m.event_id, u.vehicle_id, NOW(), NOW() FROM movement_units u JOIN movements m ON m.id = u.movement_id WHERE u.vehicle_id IS NOT NULL AND m.event_id IS NOT NULL');
        DB::statement('INSERT IGNORE INTO event_driver (event_id, driver_id, created_at, updated_at) SELECT DISTINCT event_id, driver_id, NOW(), NOW() FROM movements WHERE driver_id IS NOT NULL AND event_id IS NOT NULL');
        DB::statement('INSERT IGNORE INTO event_driver (event_id, driver_id, created_at, updated_at) SELECT DISTINCT m.event_id, u.driver_id, NOW(), NOW() FROM movement_units u JOIN movements m ON m.id = u.movement_id WHERE u.driver_id IS NOT NULL AND m.event_id IS NOT NULL');
        DB::statement('INSERT IGNORE INTO event_vehicle (event_id, vehicle_id, created_at, updated_at) SELECT e.id, v.id, NOW(), NOW() FROM events e JOIN vehicles v ON v.provider_id = e.fleet_provider_id WHERE e.fleet_provider_id IS NOT NULL');
        DB::statement('INSERT IGNORE INTO event_driver (event_id, driver_id, created_at, updated_at) SELECT e.id, d.id, NOW(), NOW() FROM events e JOIN drivers d ON d.provider_id = e.fleet_provider_id WHERE e.fleet_provider_id IS NOT NULL');
    }
};
