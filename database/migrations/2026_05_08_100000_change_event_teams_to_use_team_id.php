<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if team_id already exists (in case migration partially ran)
        $hasTeamId = Schema::hasColumn('event_teams', 'team_id');
        
        if (!$hasTeamId) {
            // Add the team_id column as nullable
            Schema::table('event_teams', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->after('event_id');
            });

            // Migrate data: populate team_id from team_code
            DB::statement('
                UPDATE event_teams et
                INNER JOIN teams t ON et.team_code = t.code
                SET et.team_id = t.id
            ');
        }

        // Check if team_code still exists
        if (Schema::hasColumn('event_teams', 'team_code')) {
            // Get existing foreign keys on team_code
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'event_teams' 
                AND COLUMN_NAME = 'team_code'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            // Drop foreign keys first (outside Schema::table)
            foreach ($foreignKeys as $fk) {
                try {
                    DB::statement("ALTER TABLE event_teams DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                } catch (\Exception $e) {
                    // Ignore if already dropped
                }
            }
            
            // Check and drop all unique constraints that include team_code
            $uniqueKeys = DB::select("
                SELECT DISTINCT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'event_teams' 
                AND CONSTRAINT_TYPE = 'UNIQUE'
                AND CONSTRAINT_NAME LIKE '%team_code%'
            ");
            
            foreach ($uniqueKeys as $uk) {
                try {
                    DB::statement("ALTER TABLE event_teams DROP INDEX {$uk->CONSTRAINT_NAME}");
                } catch (\Exception $e) {
                    // Ignore if already dropped
                }
            }
        }

        Schema::table('event_teams', function (Blueprint $table) {
            // Make team_id required if not already
            if (!DB::selectOne("SHOW COLUMNS FROM event_teams WHERE Field = 'team_id' AND `Null` = 'NO'")) {
                $table->unsignedBigInteger('team_id')->nullable(false)->change();
            }
            
            // Add foreign key if doesn't exist
            $fkExists = DB::selectOne("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'event_teams' 
                AND COLUMN_NAME = 'team_id'
                AND REFERENCED_TABLE_NAME = 'teams'
            ");
            
            if (!$fkExists) {
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            }
            
            // Add unique constraint if doesn't exist
            $uniqueExists = DB::selectOne("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'event_teams' 
                AND CONSTRAINT_TYPE = 'UNIQUE'
                AND CONSTRAINT_NAME LIKE '%event_id%team_id%'
            ");
            
            if (!$uniqueExists) {
                $table->unique(['event_id', 'team_id']);
            }
        });
        
        // Drop team_code column if it still exists (using raw SQL to avoid constraint issues)
        if (Schema::hasColumn('event_teams', 'team_code')) {
            try {
                DB::statement("ALTER TABLE event_teams DROP COLUMN team_code");
            } catch (\Exception $e) {
                // If it fails, log but continue
                \Log::warning("Could not drop team_code column: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        Schema::table('event_teams', function (Blueprint $table) {
            // Drop foreign key and unique constraint
            $table->dropForeign(['team_id']);
            $table->dropUnique(['event_id', 'team_id']);
            
            // Drop team_id column
            $table->dropColumn('team_id');
            
            // Add back team_code
            $table->string('team_code', 10)->after('event_id');
            $table->foreign('team_code')->references('code')->on('teams')->onDelete('cascade');
            $table->unique(['event_id', 'team_code']);
        });
    }
};
