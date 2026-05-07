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
        Schema::create('pma_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('role', 255);
            $table->string('org', 255)->nullable();
            $table->string('phone', 255)->nullable();
            $table->boolean('on_shift')->default(false);
            $table->boolean('disabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pma_contacts');
    }
};
