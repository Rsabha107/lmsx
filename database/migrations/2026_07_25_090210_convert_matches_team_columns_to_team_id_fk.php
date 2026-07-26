<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign('matches_team1_id_foreign');
            $table->dropForeign('matches_team2_id_foreign');
            $table->unsignedBigInteger('team1_int_id')->nullable()->after('team1_id');
            $table->unsignedBigInteger('team2_int_id')->nullable()->after('team2_id');
        });

        DB::statement('UPDATE matches m INNER JOIN teams t ON t.code = m.team1_id AND t.event_id = m.event_id SET m.team1_int_id = t.id');
        DB::statement('UPDATE matches m INNER JOIN teams t ON t.code = m.team2_id AND t.event_id = m.event_id SET m.team2_int_id = t.id');

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['team1_id', 'team2_id']);
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->renameColumn('team1_int_id', 'team1_id');
            $table->renameColumn('team2_int_id', 'team2_id');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->foreign('team1_id')->references('id')->on('teams')->onDelete('set null');
            $table->foreign('team2_id')->references('id')->on('teams')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('matches', function (Blueprint $table) {
            $table->dropForeign(['team1_id']);
            $table->dropForeign(['team2_id']);
            $table->string('team1_code')->nullable()->after('team1_id');
            $table->string('team2_code')->nullable()->after('team2_id');
        });

        DB::statement('UPDATE matches m INNER JOIN teams t ON t.id = m.team1_id SET m.team1_code = t.code');
        DB::statement('UPDATE matches m INNER JOIN teams t ON t.id = m.team2_id SET m.team2_code = t.code');

        Schema::table('matches', function (Blueprint $table) {
            $table->dropColumn(['team1_id', 'team2_id']);
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->renameColumn('team1_code', 'team1_id');
            $table->renameColumn('team2_code', 'team2_id');
        });

        Schema::table('matches', function (Blueprint $table) {
            $table->foreign('team1_id')->references('code')->on('teams')->onDelete('set null');
            $table->foreign('team2_id')->references('code')->on('teams')->onDelete('set null');
        });
    }
};
