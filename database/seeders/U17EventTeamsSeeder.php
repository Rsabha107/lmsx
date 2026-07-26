<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Event;
use App\Models\Team;
use App\Models\TeamClassification;
use Illuminate\Database\Seeder;

/**
 * Seeds the 48 national U17 teams from docs/u17 event teams.pdf
 * (group standings for a Doha-hosted U17 tournament).
 */
class U17EventTeamsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $event = Event::where('short_name', 'FU17WC')
            ->orWhere('name', 'FIFA U-17 WORLD CUP QATAR 2026')
            ->first();

        if (! $event) {
            $this->command?->warn('FIFA U-17 World Cup Qatar 2026 event not found, skipping.');

            return;
        }

        $classification = TeamClassification::firstOrCreate(
            ['name' => 'National Team'],
            ['description' => 'National team delegations', 'is_active' => true]
        );

        // Countries referenced by these teams that aren't already seeded.
        // Codes follow the tournament's own PMA codes (docs/u17 event teams.pdf).
        // Germany reuses the existing "DEU" country record rather than adding "GER".
        $countries = [
            'QAT' => ['Qatar', '🇶🇦', '+974'],
            'RSA' => ['South Africa', '🇿🇦', '+27'],
            'BOL' => ['Bolivia', '🇧🇴', '+591'],
            'MAR' => ['Morocco', '🇲🇦', '+212'],
            'NCL' => ['New Caledonia', '🇳🇨', '+687'],
            'POR' => ['Portugal', '🇵🇹', '+351'],
            'SEN' => ['Senegal', '🇸🇳', '+221'],
            'CRO' => ['Croatia', '🇭🇷', '+385'],
            'CRC' => ['Costa Rica', '🇨🇷', '+506'],
            'BEL' => ['Belgium', '🇧🇪', '+32'],
            'TUN' => ['Tunisia', '🇹🇳', '+216'],
            'FIJ' => ['Fiji', '🇫🇯', '+679'],
            'ENG' => ['England', '🏴󠁧󠁢󠁥󠁮󠁧󠁿', '+44'],
            'VEN' => ['Venezuela', '🇻🇪', '+58'],
            'HAI' => ['Haiti', '🇭🇹', '+509'],
            'EGY' => ['Egypt', '🇪🇬', '+20'],
            'MEX' => ['Mexico', '🇲🇽', '+52'],
            'KOR' => ['South Korea', '🇰🇷', '+82'],
            'CIV' => ["Côte d'Ivoire", '🇨🇮', '+225'],
            'SUI' => ['Switzerland', '🇨🇭', '+41'],
            'COL' => ['Colombia', '🇨🇴', '+57'],
            'PRK' => ['North Korea', '🇰🇵', '+850'],
            'SLV' => ['El Salvador', '🇸🇻', '+503'],
            'HON' => ['Honduras', '🇭🇳', '+504'],
            'IDN' => ['Indonesia', '🇮🇩', '+62'],
            'ZAM' => ['Zambia', '🇿🇲', '+260'],
            'USA' => ['United States', '🇺🇸', '+1'],
            'BFA' => ['Burkina Faso', '🇧🇫', '+226'],
            'TJK' => ['Tajikistan', '🇹🇯', '+992'],
            'CZE' => ['Czechia', '🇨🇿', '+420'],
            'PAR' => ['Paraguay', '🇵🇾', '+595'],
            'UZB' => ['Uzbekistan', '🇺🇿', '+998'],
            'PAN' => ['Panama', '🇵🇦', '+507'],
            'IRL' => ['Ireland', '🇮🇪', '+353'],
            'CHI' => ['Chile', '🇨🇱', '+56'],
            'CAN' => ['Canada', '🇨🇦', '+1'],
            'UGA' => ['Uganda', '🇺🇬', '+256'],
            'MLI' => ['Mali', '🇲🇱', '+223'],
            'NZL' => ['New Zealand', '🇳🇿', '+64'],
            'AUT' => ['Austria', '🇦🇹', '+43'],
            'KSA' => ['Saudi Arabia', '🇸🇦', '+966'],
        ];

        foreach ($countries as $code => [$name, $flag, $areaCode]) {
            Country::firstOrCreate(
                ['country_code' => $code],
                [
                    'country_name' => $name,
                    'flag' => $flag,
                    'area_code' => $areaCode,
                    'is_active' => true,
                ]
            );
        }

        $teams = [
            // Group A
            ['group' => 'Group A', 'code' => 'QAT-17', 'team_name' => 'Qatar U17', 'country_id' => 'QAT'],
            ['group' => 'Group A', 'code' => 'ITA-17', 'team_name' => 'Italy U17', 'country_id' => 'ITA'],
            ['group' => 'Group A', 'code' => 'RSA-17', 'team_name' => 'South Africa U17', 'country_id' => 'RSA'],
            ['group' => 'Group A', 'code' => 'BOL-17', 'team_name' => 'Bolivia U17', 'country_id' => 'BOL'],

            // Group B
            ['group' => 'Group B', 'code' => 'JPN-17', 'team_name' => 'Japan U17', 'country_id' => 'JPN'],
            ['group' => 'Group B', 'code' => 'MAR-17', 'team_name' => 'Morocco U17', 'country_id' => 'MAR'],
            ['group' => 'Group B', 'code' => 'NCL-17', 'team_name' => 'New Caledonia U17', 'country_id' => 'NCL'],
            ['group' => 'Group B', 'code' => 'POR-17', 'team_name' => 'Portugal U17', 'country_id' => 'POR'],

            // Group C
            ['group' => 'Group C', 'code' => 'SEN-17', 'team_name' => 'Senegal U17', 'country_id' => 'SEN'],
            ['group' => 'Group C', 'code' => 'CRO-17', 'team_name' => 'Croatia U17', 'country_id' => 'CRO'],
            ['group' => 'Group C', 'code' => 'CRC-17', 'team_name' => 'Costa Rica U17', 'country_id' => 'CRC'],
            ['group' => 'Group C', 'code' => 'UAE-17', 'team_name' => 'United Arab Emirates U17', 'country_id' => 'UAE'],

            // Group D
            ['group' => 'Group D', 'code' => 'ARG-17', 'team_name' => 'Argentina U17', 'country_id' => 'ARG'],
            ['group' => 'Group D', 'code' => 'BEL-17', 'team_name' => 'Belgium U17', 'country_id' => 'BEL'],
            ['group' => 'Group D', 'code' => 'TUN-17', 'team_name' => 'Tunisia U17', 'country_id' => 'TUN'],
            ['group' => 'Group D', 'code' => 'FIJ-17', 'team_name' => 'Fiji U17', 'country_id' => 'FIJ'],

            // Group E
            ['group' => 'Group E', 'code' => 'ENG-17', 'team_name' => 'England U17', 'country_id' => 'ENG'],
            ['group' => 'Group E', 'code' => 'VEN-17', 'team_name' => 'Venezuela U17', 'country_id' => 'VEN'],
            ['group' => 'Group E', 'code' => 'HAI-17', 'team_name' => 'Haiti U17', 'country_id' => 'HAI'],
            ['group' => 'Group E', 'code' => 'EGY-17', 'team_name' => 'Egypt U17', 'country_id' => 'EGY'],

            // Group F
            ['group' => 'Group F', 'code' => 'MEX-17', 'team_name' => 'Mexico U17', 'country_id' => 'MEX'],
            ['group' => 'Group F', 'code' => 'KOR-17', 'team_name' => 'South Korea U17', 'country_id' => 'KOR'],
            ['group' => 'Group F', 'code' => 'CIV-17', 'team_name' => "Côte d'Ivoire U17", 'country_id' => 'CIV'],
            ['group' => 'Group F', 'code' => 'SUI-17', 'team_name' => 'Switzerland U17', 'country_id' => 'SUI'],

            // Group G
            ['group' => 'Group G', 'code' => 'GER-17', 'team_name' => 'Germany U17', 'country_id' => 'DEU'],
            ['group' => 'Group G', 'code' => 'COL-17', 'team_name' => 'Colombia U17', 'country_id' => 'COL'],
            ['group' => 'Group G', 'code' => 'PRK-17', 'team_name' => 'North Korea U17', 'country_id' => 'PRK'],
            ['group' => 'Group G', 'code' => 'SLV-17', 'team_name' => 'El Salvador U17', 'country_id' => 'SLV'],

            // Group H
            ['group' => 'Group H', 'code' => 'BRA-17', 'team_name' => 'Brazil U17', 'country_id' => 'BRA'],
            ['group' => 'Group H', 'code' => 'HON-17', 'team_name' => 'Honduras U17', 'country_id' => 'HON'],
            ['group' => 'Group H', 'code' => 'IDN-17', 'team_name' => 'Indonesia U17', 'country_id' => 'IDN'],
            ['group' => 'Group H', 'code' => 'ZAM-17', 'team_name' => 'Zambia U17', 'country_id' => 'ZAM'],

            // Group I
            ['group' => 'Group I', 'code' => 'USA-17', 'team_name' => 'United States U17', 'country_id' => 'USA'],
            ['group' => 'Group I', 'code' => 'BFA-17', 'team_name' => 'Burkina Faso U17', 'country_id' => 'BFA'],
            ['group' => 'Group I', 'code' => 'TJK-17', 'team_name' => 'Tajikistan U17', 'country_id' => 'TJK'],
            ['group' => 'Group I', 'code' => 'CZE-17', 'team_name' => 'Czechia U17', 'country_id' => 'CZE'],

            // Group J
            ['group' => 'Group J', 'code' => 'PAR-17', 'team_name' => 'Paraguay U17', 'country_id' => 'PAR'],
            ['group' => 'Group J', 'code' => 'UZB-17', 'team_name' => 'Uzbekistan U17', 'country_id' => 'UZB'],
            ['group' => 'Group J', 'code' => 'PAN-17', 'team_name' => 'Panama U17', 'country_id' => 'PAN'],
            ['group' => 'Group J', 'code' => 'IRL-17', 'team_name' => 'Ireland U17', 'country_id' => 'IRL'],

            // Group K
            ['group' => 'Group K', 'code' => 'FRA-17', 'team_name' => 'France U17', 'country_id' => 'FRA'],
            ['group' => 'Group K', 'code' => 'CHI-17', 'team_name' => 'Chile U17', 'country_id' => 'CHI'],
            ['group' => 'Group K', 'code' => 'CAN-17', 'team_name' => 'Canada U17', 'country_id' => 'CAN'],
            ['group' => 'Group K', 'code' => 'UGA-17', 'team_name' => 'Uganda U17', 'country_id' => 'UGA'],

            // Group L
            ['group' => 'Group L', 'code' => 'MLI-17', 'team_name' => 'Mali U17', 'country_id' => 'MLI'],
            ['group' => 'Group L', 'code' => 'NZL-17', 'team_name' => 'New Zealand U17', 'country_id' => 'NZL'],
            ['group' => 'Group L', 'code' => 'AUT-17', 'team_name' => 'Austria U17', 'country_id' => 'AUT'],
            ['group' => 'Group L', 'code' => 'KSA-17', 'team_name' => 'Saudi Arabia U17', 'country_id' => 'KSA'],
        ];

        $flagsByCountry = Country::pluck('flag', 'country_code');

        foreach ($teams as $team) {
            Team::updateOrCreate(
                ['code' => $team['code'], 'event_id' => $event->id],
                [
                    'team_name' => $team['team_name'],
                    'country_id' => $team['country_id'],
                    'flag' => $flagsByCountry[$team['country_id']] ?? null,
                    'group_pool' => $team['group'],
                    'classification_type_id' => $classification->id,
                    'is_active' => true,
                ]
            );
        }
    }
}
