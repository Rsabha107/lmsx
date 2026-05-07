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
        Schema::create('checkpoint_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 'TPL-ARR-AIRPORT'
            $table->string('name'); // e.g., 'Airport Arrival Template'
            $table->enum('movement_type', ['arrival', 'departure', 'transfer', 'training', 'match'])->default('transfer');
            $table->text('description')->nullable();
            $table->integer('estimated_duration_minutes')->nullable(); // Average completion time
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkpoint_templates');
    }
};
