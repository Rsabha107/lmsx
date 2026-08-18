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
        // Was a raw MySQL-only "MODIFY COLUMN ... ENUM" statement, which broke
        // the whole test suite's RefreshDatabase on sqlite (no such syntax).
        // The schema-builder ->change() form is portable across drivers and
        // is already used for this exact table/pattern in the sibling
        // functional_area migration, so this is a drop-in replacement.
        Schema::table('movement_templates', function (Blueprint $table) {
            $table->enum('scenario_type', ['match_day', 'training_day', 'arrival_day', 'departure_day', 'full_day', 'custom'])
                ->default('custom')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movement_templates', function (Blueprint $table) {
            $table->enum('scenario_type', ['match_day', 'training_day', 'arrival_day', 'departure_day', 'custom'])
                ->default('custom')
                ->change();
        });
    }
};
