<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Separates when a checkpoint happened, when the server heard about it, and where the time came from. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_checkpoints', function (Blueprint $table) {
            // What the device claimed, before any clock correction.
            $table->dateTime('event_at')->nullable();
            // When the server accepted the completion.
            $table->dateTime('received_at')->nullable();
            // Server time minus the device clock when it sent the request; positive = device behind.
            $table->integer('clock_skew_seconds')->nullable();
            // device | manual | server | override
            $table->string('time_source', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_checkpoints', function (Blueprint $table) {
            $table->dropColumn(['event_at', 'received_at', 'clock_skew_seconds', 'time_source']);
        });
    }
};
