<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->string('team_code', 10);
            $table->string('group_pool', 50)->nullable();
            $table->unsignedBigInteger('classification_type_id')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'team_code']);
            $table->foreign('team_code')->references('code')->on('teams')->onDelete('cascade');
            $table->foreign('classification_type_id')->references('id')->on('team_classifications')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_teams');
    }
};
