<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('flight_status', 50)->nullable()->after('flight_number');
            $table->datetime('actual_arrival_date_time')->nullable()->after('arrival_date_time');
            $table->datetime('actual_departure_date_time')->nullable()->after('departure_date_time');
            $table->integer('arrival_delay_minutes')->nullable()->after('actual_arrival_date_time');
            $table->integer('departure_delay_minutes')->nullable()->after('actual_departure_date_time');
            $table->datetime('flight_synced_at')->nullable()->after('departure_delay_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'flight_status',
                'actual_arrival_date_time',
                'actual_departure_date_time',
                'arrival_delay_minutes',
                'departure_delay_minutes',
                'flight_synced_at',
            ]);
        });
    }
};
