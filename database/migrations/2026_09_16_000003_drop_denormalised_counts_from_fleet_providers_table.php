<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * These counters were seeded once and never maintained, so they drifted from
 * reality. Vehicle/driver totals are now derived from the relations instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fleet_providers', function (Blueprint $table) {
            $table->dropColumn(['total_vehicles', 'total_drivers']);
        });
    }

    public function down(): void
    {
        Schema::table('fleet_providers', function (Blueprint $table) {
            $table->integer('total_vehicles')->default(0);
            $table->integer('total_drivers')->default(0);
        });
    }
};
