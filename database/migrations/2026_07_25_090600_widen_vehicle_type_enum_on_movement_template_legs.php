<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// MovementTemplateLibrarySeeder seeds 'coach' and 'van' values that aren't
// in the original enum, causing a truncation error on a fresh seed run.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE movement_template_legs MODIFY vehicle_type ENUM('bus', 'coach', 'van', 'walk', 'car', 'other') DEFAULT 'bus'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE movement_template_legs MODIFY vehicle_type ENUM('bus', 'walk', 'car', 'other') DEFAULT 'bus'");
    }
};
