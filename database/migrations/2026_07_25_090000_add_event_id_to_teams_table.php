<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unsignedBigInteger('event_id')->nullable()->after('id');
        });

        DB::statement('UPDATE teams t INNER JOIN event_teams et ON et.team_id = t.id SET t.event_id = et.event_id');

        // Backfill any team with no event_teams row to the FIFA U-17 World Cup event.
        $fallbackEventId = DB::table('events')->where('short_name', 'FU17WC')->value('id');

        if ($fallbackEventId) {
            DB::table('teams')->whereNull('event_id')->update(['event_id' => $fallbackEventId]);
        }

        $orphaned = DB::table('teams')->whereNull('event_id')->count();

        if ($orphaned > 0) {
            throw new \RuntimeException("Migration aborted: {$orphaned} team(s) still have no event_id after backfill.");
        }
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('event_id');
        });
    }
};
