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
        Schema::create('fleet_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20);
            $table->string('name', 255);
            $table->string('contact_person', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->integer('total_vehicles')->default(0);
            $table->integer('total_drivers')->default(0);
            $table->decimal('rating', 2, 1)->default(0.0);
            $table->enum('status', ['active', 'standby'])->default('active');
            $table->text('notes')->nullable();
            $table->integer('is_active')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_providers');
    }
};
