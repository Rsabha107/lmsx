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
            // Add accommodation_id column after flight_id
            $table->foreignId('accommodation_id')
                  ->nullable()
                  ->after('flight_id')
                  ->constrained('team_stays')
                  ->onDelete('set null');
            
            // Add index for accommodation-based queries
            $table->index('accommodation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->dropForeign(['accommodation_id']);
            $table->dropColumn('accommodation_id');
        });
    }
};
