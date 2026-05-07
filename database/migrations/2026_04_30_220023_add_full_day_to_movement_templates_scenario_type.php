<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the scenario_type enum to add 'full_day'
        DB::statement("ALTER TABLE movement_templates MODIFY COLUMN scenario_type ENUM('match_day', 'training_day', 'arrival_day', 'departure_day', 'full_day', 'custom') DEFAULT 'custom'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE movement_templates MODIFY COLUMN scenario_type ENUM('match_day', 'training_day', 'arrival_day', 'departure_day', 'custom') DEFAULT 'custom'");
    }
};
