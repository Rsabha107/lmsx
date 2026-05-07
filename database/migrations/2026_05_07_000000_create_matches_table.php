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
        Schema::create('matches', function (Blueprint $table) {
            $table->id();
            $table->string('event')->nullable();
            $table->string('venue')->nullable();
            $table->string('match_number')->unique();
            $table->string('team1_id')->nullable();
            $table->string('team2_id')->nullable();
            $table->string('stage')->nullable();
            $table->dateTime('match_date')->nullable();
            $table->dateTime('gates_opening')->nullable();
            $table->dateTime('kick_off')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('team1_id')->references('code')->on('teams')->onDelete('set null');
            $table->foreign('team2_id')->references('code')->on('teams')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('matches');
    }
};
