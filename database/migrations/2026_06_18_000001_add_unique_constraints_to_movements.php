<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add composite indexes to help detect duplicate movements.
     * Note: We handle uniqueness validation in the application layer
     * because MySQL doesn't support partial unique indexes like PostgreSQL.
     */
    public function up(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            // Index for quick duplicate detection on arrivals/departures
            $table->index(['team_id', 'flight_id', 'kind'], 'idx_team_flight_kind');
            
            // Index for quick duplicate detection on match movements
            $table->index(['team_id', 'match_id', 'kind'], 'idx_team_match_kind');
            
            // Index for detecting similar transfers/training/daily_ops
            $table->index(['team_id', 'kind', 'from_location', 'to_location', 'window_start'], 'idx_team_movement_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movements', function (Blueprint $table) {
            $table->dropIndex('idx_team_flight_kind');
            $table->dropIndex('idx_team_match_kind');
            $table->dropIndex('idx_team_movement_details');
        });
    }
};
