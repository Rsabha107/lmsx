<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** The sample people the old ContactSeeder inserted, matched on name and phone. */
    private const SAMPLES = [
        ['Sarah Johnson', '+33 6 12 34 56 78'],
        ['Michael Chen', '+33 6 23 45 67 89'],
        ['Emma Williams', '+33 6 34 56 78 90'],
        ['David Martinez', '+33 6 45 67 89 01'],
        ['Lisa Anderson', '+33 6 56 78 90 12'],
        ['James Wilson', '+33 6 67 89 01 23'],
        ['Sophie Dubois', '+33 6 78 90 12 34'],
        ['Robert Taylor', '+33 6 89 01 23 45'],
        ['Maria Garcia', '+33 6 90 12 34 56'],
        ['Thomas Brown', '+33 6 01 23 45 67'],
        ['Anna Schmidt', '+33 6 12 34 56 79'],
        ['Carlos Rodriguez', '+33 6 23 45 67 80'],
    ];

    public function up(): void
    {
        DB::table('pma_contacts')
            ->whereNull('user_id')
            ->where(function ($q) {
                foreach (self::SAMPLES as [$name, $phone]) {
                    $q->orWhere(fn ($w) => $w->where('name', $name)->where('phone', $phone));
                }
            })
            ->delete();
    }

    public function down(): void
    {
        // Sample data is not restored.
    }
};
