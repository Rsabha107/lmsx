<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $countries = [
            [
                'country_code' => 'FRA',
                'country_name' => 'France',
                'area_code' => '+33',
                'flag' => '🇫🇷',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_code' => 'NOR',
                'country_name' => 'Norway',
                'area_code' => '+47',
                'flag' => '🇳🇴',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_code' => 'UAE',
                'country_name' => 'United Arab Emirates',
                'area_code' => '+971',
                'flag' => '🇦🇪',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_code' => 'ARG',
                'country_name' => 'Argentina',
                'area_code' => '+54',
                'flag' => '🇦🇷',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_code' => 'JPN',
                'country_name' => 'Japan',
                'area_code' => '+81',
                'flag' => '🇯🇵',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_code' => 'GBR',
                'country_name' => 'United Kingdom',
                'area_code' => '+44',
                'flag' => '🇬🇧',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_code' => 'DEU',
                'country_name' => 'Germany',
                'area_code' => '+49',
                'flag' => '🇩🇪',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_code' => 'ESP',
                'country_name' => 'Spain',
                'area_code' => '+34',
                'flag' => '🇪🇸',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_code' => 'ITA',
                'country_name' => 'Italy',
                'area_code' => '+39',
                'flag' => '🇮🇹',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'country_code' => 'BRA',
                'country_name' => 'Brazil',
                'area_code' => '+55',
                'flag' => '🇧🇷',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('countries')->insert($countries);
    }
}
