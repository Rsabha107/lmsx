<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Failed refreshes are visible too: flight_synced_at stays the last successful one.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_flights', function (Blueprint $table) {
            $table->timestamp('flight_sync_attempted_at')->nullable()->after('flight_synced_at');
            $table->string('flight_sync_error', 255)->nullable()->after('flight_sync_attempted_at');
        });
    }

    public function down(): void
    {
        Schema::table('team_flights', function (Blueprint $table) {
            $table->dropColumn(['flight_sync_attempted_at', 'flight_sync_error']);
        });
    }
};
