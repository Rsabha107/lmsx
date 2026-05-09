<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_stays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->string('team_code', 10);
            $table->string('hotel_name')->nullable();
            $table->string('address')->nullable();
            $table->string('training_ground')->nullable();
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->unsignedInteger('room_count')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('team_code')->references('code')->on('teams')->onDelete('cascade');
            $table->index(['event_id', 'team_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_stays');
    }
};
