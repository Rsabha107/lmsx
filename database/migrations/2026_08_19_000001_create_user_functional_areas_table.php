<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_functional_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('functional_area', ['LOG', 'AND', 'MOB']);
            $table->timestamps();

            $table->unique(['user_id', 'functional_area']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_functional_areas');
    }
};
