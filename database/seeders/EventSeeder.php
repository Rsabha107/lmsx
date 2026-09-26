<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Country::firstOrCreate(
            ['country_code' => 'QAT'],
            ['country_name' => 'Qatar', 'flag' => '🇶🇦', 'area_code' => '+974', 'is_active' => true]
        );

        Event::firstOrCreate(
            ['short_name' => 'FU17WC'],
            [
                'name' => 'FIFA U-17 WORLD CUP QATAR 2026',
                'host_country' => 'QAT',
                'start_date' => '2026-11-10',
                'end_date' => '2026-12-02',
                'event_logo' => '',
                'active_flag' => true,
            ]
        );
    }
}
