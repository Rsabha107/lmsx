<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_flights', function (Blueprint $table) {
            $table->unsignedInteger('party_size_total')->nullable()->after('manifest');
            $table->unsignedInteger('party_size_players')->nullable()->after('party_size_total');
            $table->unsignedInteger('party_size_staff')->nullable()->after('party_size_players');
        });
    }

    public function down(): void
    {
        Schema::table('team_flights', function (Blueprint $table) {
            $table->dropColumn(['party_size_total', 'party_size_players', 'party_size_staff']);
        });
    }
};
