<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supervisors on a movement beyond the lead field_supervisor_id. They see and
 * work its job in the mobile app; the lead stays accountable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_supervisors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['movement_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_supervisors');
    }
};
