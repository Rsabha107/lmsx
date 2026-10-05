<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra vehicle + driver pairs on a movement, beyond the lead vehicle/driver
 * columns (e.g. a second truck for an arrival). The job reads them through its movement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['vehicle_id']);
            $table->index(['driver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_units');
    }
};
