<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // MySQL doesn't allow modifying ENUM directly, need to use raw SQL
        DB::statement("ALTER TABLE movements MODIFY COLUMN kind ENUM('arrival', 'departure', 'transfer', 'training', 'match', 'daily_ops') NOT NULL DEFAULT 'transfer'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE movements MODIFY COLUMN kind ENUM('arrival', 'departure', 'transfer', 'training', 'match') NOT NULL DEFAULT 'transfer'");
    }
};
