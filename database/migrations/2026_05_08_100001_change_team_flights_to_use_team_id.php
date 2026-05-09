<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if team_id already exists
        if (!Schema::hasColumn('team_flights', 'team_id')) {
            Schema::table('team_flights', function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->after('event_id');
            });

            // Migrate data
            DB::statement('
                UPDATE team_flights tf
                INNER JOIN teams t ON tf.team_code = t.code
                SET tf.team_id = t.id
            ');
        }

        // Drop constraints if team_code exists
        if (Schema::hasColumn('team_flights', 'team_code')) {
            $foreignKeys = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'team_flights' 
                AND COLUMN_NAME = 'team_code'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ");

            // Drop foreign keys first
            foreach ($foreignKeys as $fk) {
                try {
                    DB::statement("ALTER TABLE team_flights DROP FOREIGN KEY {$fk->CONSTRAINT_NAME}");
                } catch (\Exception $e) {
                    // Ignore if already dropped
                }
            }
            
            // Drop all indexes that include team_code
            $indexes = DB::select("
                SELECT DISTINCT INDEX_NAME 
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'team_flights' 
                AND COLUMN_NAME = 'team_code'
                AND INDEX_NAME != 'PRIMARY'
            ");
            
            foreach ($indexes as $idx) {
                try {
                    DB::statement("ALTER TABLE team_flights DROP INDEX {$idx->INDEX_NAME}");
                } catch (\Exception $e) {
                    // Ignore if already dropped
                }
            }
        }

        Schema::table('team_flights', function (Blueprint $table) {
            // Make team_id required if not already
            if (!DB::selectOne("SHOW COLUMNS FROM team_flights WHERE Field = 'team_id' AND `Null` = 'NO'")) {
                $table->unsignedBigInteger('team_id')->nullable(false)->change();
            }
            
            // Add foreign key if doesn't exist
            if (!DB::selectOne("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'team_flights' 
                AND COLUMN_NAME = 'team_id'
                AND REFERENCED_TABLE_NAME = 'teams'
            ")) {
                $table->foreign('team_id')->references('id')->on('teams')->onDelete('cascade');
            }
            
            // Add index if doesn't exist
            if (!DB::selectOne("
                SELECT INDEX_NAME 
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'team_flights' 
                AND INDEX_NAME = 'team_flights_event_id_team_id_direction_index'
            ")) {
                $table->index(['event_id', 'team_id', 'direction']);
            }
        });
        
        // Drop team_code column if it still exists (using raw SQL)
        if (Schema::hasColumn('team_flights', 'team_code')) {
            try {
                DB::statement("ALTER TABLE team_flights DROP COLUMN team_code");
            } catch (\Exception $e) {
                \Log::warning("Could not drop team_code column: " . $e->getMessage());
            }
        }
    }

    public function down(): void
    {
        Schema::table('team_flights', function (Blueprint $table) {
            // Drop foreign key and index
            $table->dropForeign(['team_id']);
            $table->dropIndex(['event_id', 'team_id', 'direction']);
            
            // Drop team_id column
            $table->dropColumn('team_id');
            
            // Add back team_code
            $table->string('team_code', 10)->after('event_id');
            $table->foreign('team_code')->references('code')->on('teams')->onDelete('cascade');
            $table->index(['event_id', 'team_code', 'direction']);
        });
    }
};
