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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable();
            $table->unsignedBigInteger('provider_id')->nullable();
            $table->string('plate_number', 50)->nullable();
            $table->string('vehicle_type', 100)->nullable();
            $table->integer('capacity')->nullable();
            $table->string('fuel_level', 50)->nullable();
            $table->enum('status', ['available', 'on_job', 'maintenance', 'standby'])->default('available');
            $table->integer('is_active')->nullable();
            $table->tinyText('notes')->nullable();
            $table->timestamps();
            $table->enum('category', ['Team', 'Official', 'VIP', 'Media'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
