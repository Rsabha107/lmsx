<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * BUS-flight movements (flight_number === 'BUS' — the team travels by
     * road, not an actual flight) have no reference time to derive a
     * window from, so window_start/window_end must be nullable.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE movements MODIFY window_start DATETIME NULL');
        DB::statement('ALTER TABLE movements MODIFY window_end DATETIME NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE movements MODIFY window_start DATETIME NOT NULL');
        DB::statement('ALTER TABLE movements MODIFY window_end DATETIME NOT NULL');
    }
};
