<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            // Add flight_id column after team_id
            $table->foreignId('flight_id')
                  ->nullable()
                  ->after('team_id')
                  ->constrained('team_flights')
                  ->onDelete('set null');
            
            // Add index for flight-based queries
            $table->index('flight_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->dropForeign(['flight_id']);
            $table->dropColumn('flight_id');
        });
    }
};
