<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('event_teams');
    }

    public function down(): void
    {
        Schema::create('event_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('team_id')->constrained('teams')->onDelete('cascade');
            $table->string('group_pool', 50)->nullable();
            $table->unsignedBigInteger('classification_type_id')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'team_id']);
        });
    }
};
