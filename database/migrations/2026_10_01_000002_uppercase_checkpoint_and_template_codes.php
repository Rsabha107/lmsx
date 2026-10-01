<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['checkpoints', 'checkpoint_templates', 'movement_templates'] as $table) {
            DB::table($table)->update(['code' => DB::raw('UPPER(TRIM(code))')]);
        }
    }

    public function down(): void
    {
        // Original casing is not recoverable.
    }
};
