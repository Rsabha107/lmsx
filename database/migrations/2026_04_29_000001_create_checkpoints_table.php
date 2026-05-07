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
        Schema::create('checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g., 'CKP-DISPATCH'
            $table->string('name'); // e.g., 'Vehicle Dispatch'
            $table->enum('type', ['dispatch', 'arrival', 'boarding', 'departure', 'handoff', 'manual', 'auto'])->default('manual');
            $table->text('description')->nullable();
            $table->enum('capture_method', ['auto', 'manual', 'gps', 'photo', 'signature'])->default('manual');
            $table->boolean('requires_photo')->default(false);
            $table->boolean('requires_signature')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkpoints');
    }
};
