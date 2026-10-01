<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** M17 -> TRP-00017; the number is kept so references in notes/exports still line up. */
    public function up(): void
    {
        DB::table('movements')
            ->whereRaw("code REGEXP '^M[0-9]+$'")
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('movements')->where('id', $row->id)
                        ->update(['code' => sprintf('TRP-%05d', (int) substr($row->code, 1))]);
                }
            });
    }

    public function down(): void
    {
        DB::table('movements')
            ->whereRaw("code REGEXP '^TRP-[0-9]+$'")
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('movements')->where('id', $row->id)
                        ->update(['code' => 'M'.(int) substr($row->code, 4)]);
                }
            });
    }
};
