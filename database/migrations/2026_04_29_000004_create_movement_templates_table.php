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
        Schema::create('movement_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 'MVT-MATCH-STD'
            $table->string('name'); // e.g., 'Match Day - Standard'
            $table->text('description')->nullable();
            $table->enum('scenario_type', ['match_day', 'training_day', 'arrival_day', 'departure_day', 'custom'])->default('custom');
            $table->integer('total_legs')->default(0); // Number of movements in this template
            $table->integer('estimated_duration_minutes')->nullable(); // Total duration
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movement_templates');
    }
};
