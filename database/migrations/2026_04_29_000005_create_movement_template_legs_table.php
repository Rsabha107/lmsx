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
        Schema::create('movement_template_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_template_id')->constrained()->onDelete('cascade');
            $table->foreignId('checkpoint_template_id')->constrained()->onDelete('restrict');
            $table->integer('order')->default(0); // Leg sequence (L1, L2, L3...)
            $table->string('name')->nullable(); // e.g., 'Pre-match transfer'
            $table->enum('leg_type', ['arrival', 'departure', 'transfer', 'training', 'match'])->nullable();
            $table->string('from_location')->nullable(); // e.g., 'Team Hotel'
            $table->string('to_location')->nullable(); // e.g., 'Stadium'
            $table->integer('estimated_duration_minutes')->nullable();
            $table->enum('vehicle_type', ['bus', 'walk', 'car', 'other'])->default('bus');
            $table->integer('estimated_passengers')->nullable();
            $table->timestamps();

            $table->index(['movement_template_id', 'order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movement_template_legs');
    }
};
