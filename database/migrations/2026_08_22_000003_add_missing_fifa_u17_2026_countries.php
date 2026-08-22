<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $countries = [
            ['country_code' => 'GRE', 'country_name' => 'Greece', 'area_code' => '+30', 'flag' => '🇬🇷'],
            ['country_code' => 'ECU', 'country_name' => 'Ecuador', 'area_code' => '+593', 'flag' => '🇪🇨'],
            ['country_code' => 'AUS', 'country_name' => 'Australia', 'area_code' => '+61', 'flag' => '🇦🇺'],
            ['country_code' => 'DNK', 'country_name' => 'Denmark', 'area_code' => '+45', 'flag' => '🇩🇰'],
            ['country_code' => 'MOZ', 'country_name' => 'Mozambique', 'area_code' => '+258', 'flag' => '🇲🇿'],
            ['country_code' => 'URU', 'country_name' => 'Uruguay', 'area_code' => '+598', 'flag' => '🇺🇾'],
            ['country_code' => 'JAM', 'country_name' => 'Jamaica', 'area_code' => '+1876', 'flag' => '🇯🇲'],
            ['country_code' => 'CUB', 'country_name' => 'Cuba', 'area_code' => '+53', 'flag' => '🇨🇺'],
            ['country_code' => 'VIE', 'country_name' => 'Vietnam', 'area_code' => '+84', 'flag' => '🇻🇳'],
            ['country_code' => 'CHN', 'country_name' => 'China', 'area_code' => '+86', 'flag' => '🇨🇳'],
            ['country_code' => 'TAN', 'country_name' => 'Tanzania', 'area_code' => '+255', 'flag' => '🇹🇿'],
            ['country_code' => 'ALG', 'country_name' => 'Algeria', 'area_code' => '+213', 'flag' => '🇩🇿'],
            ['country_code' => 'MNE', 'country_name' => 'Montenegro', 'area_code' => '+382', 'flag' => '🇲🇪'],
            ['country_code' => 'CMR', 'country_name' => 'Cameroon', 'area_code' => '+237', 'flag' => '🇨🇲'],
            ['country_code' => 'ROU', 'country_name' => 'Romania', 'area_code' => '+40', 'flag' => '🇷🇴'],
            ['country_code' => 'SRB', 'country_name' => 'Serbia', 'area_code' => '+381', 'flag' => '🇷🇸'],
        ];

        foreach ($countries as $country) {
            DB::table('countries')->insertOrIgnore([
                ...$country,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('countries')->whereIn('country_code', [
            'GRE', 'ECU', 'AUS', 'DNK', 'MOZ', 'URU', 'JAM', 'CUB',
            'VIE', 'CHN', 'TAN', 'ALG', 'MNE', 'CMR', 'ROU', 'SRB',
        ])->delete();
    }
};
