<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Team;
use App\Models\TeamStay;
use Illuminate\Database\Seeder;

/**
 * Links the teams from U17EventTeamsSeeder to the FIFA U-17 World Cup Qatar 2026
 * event, and seeds their base camp hotel / training site from
 * docs/u17 event teams.pdf.
 */
class U17EventTeamStaysSeeder extends Seeder
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

        $trainingSite = 'Al Thumama Training Pitches / Al Erssal Training Ground';

        $teams = [
            // Group A
            ['code' => 'QAT-17', 'group' => 'Group A', 'hotel_name' => 'Al Messila, A Luxury Collection Resort & Spa'],
            ['code' => 'ITA-17', 'group' => 'Group A', 'hotel_name' => 'Ezdan Hotel'],
            ['code' => 'RSA-17', 'group' => 'Group A', 'hotel_name' => 'Holiday Villa Hotel & Residence Doha'],
            ['code' => 'BOL-17', 'group' => 'Group A', 'hotel_name' => 'Radisson Blu Hotel Doha'],

            // Group B
            ['code' => 'JPN-17', 'group' => 'Group B', 'hotel_name' => 'Radisson Blu Hotel Doha'],
            ['code' => 'MAR-17', 'group' => 'Group B', 'hotel_name' => 'Crowne Plaza Doha - The Business Park'],
            ['code' => 'NCL-17', 'group' => 'Group B', 'hotel_name' => 'Ezdan Hotel'],
            ['code' => 'POR-17', 'group' => 'Group B', 'hotel_name' => 'Holiday Villa Hotel & Residence Doha'],

            // Group C
            ['code' => 'SEN-17', 'group' => 'Group C', 'hotel_name' => 'Holiday Villa Hotel & Residence Doha'],
            ['code' => 'CRO-17', 'group' => 'Group C', 'hotel_name' => 'Radisson Blu Hotel Doha'],
            ['code' => 'CRC-17', 'group' => 'Group C', 'hotel_name' => 'Crowne Plaza Doha - The Business Park'],
            ['code' => 'UAE-17', 'group' => 'Group C', 'hotel_name' => 'Holiday Inn Doha - The Business Park'],

            // Group D
            ['code' => 'ARG-17', 'group' => 'Group D', 'hotel_name' => 'Holiday Inn Doha - The Business Park'],
            ['code' => 'BEL-17', 'group' => 'Group D', 'hotel_name' => 'Holiday Villa Hotel & Residence Doha'],
            ['code' => 'TUN-17', 'group' => 'Group D', 'hotel_name' => 'Radisson Blu Hotel Doha'],
            ['code' => 'FIJ-17', 'group' => 'Group D', 'hotel_name' => 'Dusitd2 Salwa Doha'],

            // Group E
            ['code' => 'ENG-17', 'group' => 'Group E', 'hotel_name' => 'Dusitd2 Salwa Doha'],
            ['code' => 'VEN-17', 'group' => 'Group E', 'hotel_name' => 'Holiday Inn Doha - The Business Park'],
            ['code' => 'HAI-17', 'group' => 'Group E', 'hotel_name' => 'Holiday Villa Hotel & Residence Doha'],
            ['code' => 'EGY-17', 'group' => 'Group E', 'hotel_name' => 'Radisson Blu Hotel Doha'],

            // Group F
            ['code' => 'MEX-17', 'group' => 'Group F', 'hotel_name' => 'Radisson Blu Hotel Doha'],
            ['code' => 'KOR-17', 'group' => 'Group F', 'hotel_name' => 'Dusitd2 Salwa Doha'],
            ['code' => 'CIV-17', 'group' => 'Group F', 'hotel_name' => 'Holiday Inn Doha - The Business Park'],
            ['code' => 'SUI-17', 'group' => 'Group F', 'hotel_name' => 'Millenium Hotel Doha'],

            // Group G
            ['code' => 'GER-17', 'group' => 'Group G', 'hotel_name' => 'Millenium Hotel Doha'],
            ['code' => 'COL-17', 'group' => 'Group G', 'hotel_name' => 'Warwick Doha Hotel'],
            ['code' => 'PRK-17', 'group' => 'Group G', 'hotel_name' => 'Crowne Plaza Doha - The Business Park'],
            ['code' => 'SLV-17', 'group' => 'Group G', 'hotel_name' => 'Holiday Villa Hotel & Residence Doha'],

            // Group H
            ['code' => 'BRA-17', 'group' => 'Group H', 'hotel_name' => 'Holiday Villa Hotel & Residence Doha'],
            ['code' => 'HON-17', 'group' => 'Group H', 'hotel_name' => 'Millenium Hotel Doha'],
            ['code' => 'IDN-17', 'group' => 'Group H', 'hotel_name' => 'Warwick Doha Hotel'],
            ['code' => 'ZAM-17', 'group' => 'Group H', 'hotel_name' => 'Dusitd2 Salwa Doha'],

            // Group I
            ['code' => 'USA-17', 'group' => 'Group I', 'hotel_name' => 'Dusitd2 Salwa Doha'],
            ['code' => 'BFA-17', 'group' => 'Group I', 'hotel_name' => 'Holiday Villa Hotel & Residence Doha'],
            ['code' => 'TJK-17', 'group' => 'Group I', 'hotel_name' => 'Radisson Blu Hotel Doha'],
            ['code' => 'CZE-17', 'group' => 'Group I', 'hotel_name' => 'Warwick Doha Hotel'],

            // Group J
            ['code' => 'PAR-17', 'group' => 'Group J', 'hotel_name' => 'Wyndham, Grand Regency Doha Trademark Collection'],
            ['code' => 'UZB-17', 'group' => 'Group J', 'hotel_name' => 'Ezdan Hotel'],
            ['code' => 'PAN-17', 'group' => 'Group J', 'hotel_name' => 'Holiday Villa Hotel & Residence Doha'],
            ['code' => 'IRL-17', 'group' => 'Group J', 'hotel_name' => 'Radisson Blu Hotel Doha'],

            // Group K
            ['code' => 'FRA-17', 'group' => 'Group K', 'hotel_name' => 'Radisson Blu Hotel Doha'],
            ['code' => 'CHI-17', 'group' => 'Group K', 'hotel_name' => 'Wyndham, Grand Regency Doha Trademark Collection'],
            ['code' => 'CAN-17', 'group' => 'Group K', 'hotel_name' => 'Ezdan Hotel'],
            ['code' => 'UGA-17', 'group' => 'Group K', 'hotel_name' => 'Holiday Villa Hotel & Residence Doha'],

            // Group L
            ['code' => 'MLI-17', 'group' => 'Group L', 'hotel_name' => 'Holiday Villa Hotel & Residence Doha'],
            ['code' => 'NZL-17', 'group' => 'Group L', 'hotel_name' => 'Radisson Blu Hotel Doha'],
            ['code' => 'AUT-17', 'group' => 'Group L', 'hotel_name' => 'Wyndham, Grand Regency Doha Trademark Collection'],
            ['code' => 'KSA-17', 'group' => 'Group L', 'hotel_name' => 'Ezdan Hotel'],
        ];

        $teamIdsByCode = Team::where('event_id', $event->id)
            ->whereIn('code', array_column($teams, 'code'))
            ->pluck('id', 'code');

        foreach ($teams as $row) {
            $teamId = $teamIdsByCode[$row['code']] ?? null;

            if (! $teamId) {
                $this->command?->warn("Team {$row['code']} not found, skipping.");

                continue;
            }

            TeamStay::updateOrCreate(
                ['event_id' => $event->id, 'team_id' => $teamId],
                [
                    'hotel_name' => $row['hotel_name'],
                    'training_ground' => $trainingSite,
                ]
            );
        }
    }
}
