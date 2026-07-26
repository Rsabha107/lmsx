<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('team_flights', function (Blueprint $table) {
            $table->unsignedInteger('planned_bags')->nullable()->after('party_size_staff');
        });
    }

    public function down(): void
    {
        Schema::table('team_flights', function (Blueprint $table) {
            $table->dropColumn('planned_bags');
        });
    }
};
