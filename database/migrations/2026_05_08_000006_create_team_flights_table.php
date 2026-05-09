<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->string('team_code', 10);
            $table->string('direction', 20);        // arrival | departure
            $table->string('flight_number', 20)->nullable();
            $table->unsignedBigInteger('origin_airport_id')->nullable();
            $table->unsignedBigInteger('destination_airport_id')->nullable();
            $table->string('terminal', 50)->nullable();
            $table->string('gate', 50)->nullable();
            $table->datetime('scheduled_at')->nullable();   // planned time (never overwritten)
            $table->datetime('estimated_at')->nullable();   // from airline/API
            $table->datetime('actual_at')->nullable();      // real time from flight sync
            $table->integer('delay_minutes')->nullable();
            $table->string('flight_status', 50)->nullable();
            $table->datetime('flight_synced_at')->nullable();
            $table->json('manifest')->nullable();           // multi-flight group manifest
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('team_code')->references('code')->on('teams')->onDelete('cascade');
            $table->foreign('origin_airport_id')->references('id')->on('airports')->onDelete('set null');
            $table->foreign('destination_airport_id')->references('id')->on('airports')->onDelete('set null');

            $table->index(['event_id', 'team_code', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_flights');
    }
};
