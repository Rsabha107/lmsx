<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\TeamClassification;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the Club classification ID
        $clubClassification = TeamClassification::where('name', 'Club')->first();
        
        $teams = [
            [
                'code' => 'MER',
                'team_name' => 'FC Meridian',
                'country_id' => 'FRA',
                'flag' => '🇫🇷',
                'group_pool' => 'Group A',
                'classification_type_id' => $clubClassification->id,
                'party_size_total' => 45,
                'party_size_players' => 23,
                'party_size_staff' => 22,
                'hotel_name' => 'Hotel Aurora',
                'training_ground' => 'Training Ground A',
                'arrival_date_time' => '2026-04-15 14:20:00',
                'departure_date_time' => '2026-05-02 10:30:00',
                'head_of_delegation' => 'Pierre Dubois',
                'sc_liaison_name' => 'P. Anand',
                'sc_liaison_phone' => '+33 6 12 34 56 78',
                'bib_accent_color' => '#0055A4',
                'notes' => 'VIP delegation, requires extra security',
                'is_active' => true,
            ],
            [
                'code' => 'NOR',
                'team_name' => 'Nordstad FK',
                'country_id' => 'NOR',
                'flag' => '🇳🇴',
                'group_pool' => 'Group A',
                'classification_type_id' => $clubClassification->id,
                'party_size_total' => 42,
                'party_size_players' => 23,
                'party_size_staff' => 19,
                'hotel_name' => 'Hotel Verdi',
                'training_ground' => 'Training Ground B',
                'arrival_date_time' => '2026-04-14 18:45:00',
                'departure_date_time' => '2026-05-01 16:00:00',
                'head_of_delegation' => 'Lars Hansen',
                'sc_liaison_name' => 'H. Stein',
                'sc_liaison_phone' => '+47 98 76 54 32',
                'bib_accent_color' => '#BA0C2F',
                'notes' => null,
                'is_active' => true,
            ],
            [
                'code' => 'SAH',
                'team_name' => 'Al-Sahra SC',
                'country_id' => 'UAE',
                'flag' => '🇦🇪',
                'group_pool' => 'Group B',
                'classification_type_id' => $clubClassification->id,
                'party_size_total' => 48,
                'party_size_players' => 23,
                'party_size_staff' => 25,
                'hotel_name' => 'Hotel Aurora',
                'training_ground' => 'Training Ground C',
                'arrival_date_time' => '2026-04-16 09:30:00',
                'departure_date_time' => '2026-05-03 08:00:00',
                'head_of_delegation' => 'Ahmed Al-Mansouri',
                'sc_liaison_name' => 'S. Park',
                'sc_liaison_phone' => '+971 50 123 4567',
                'bib_accent_color' => '#00843D',
                'notes' => 'Dietary requirements: Halal food only',
                'is_active' => true,
            ],
            [
                'code' => 'PAM',
                'team_name' => 'Club Pampas',
                'country_id' => 'ARG',
                'flag' => '🇦🇷',
                'group_pool' => 'Group B',
                'classification_type_id' => $clubClassification->id,
                'party_size_total' => 44,
                'party_size_players' => 23,
                'party_size_staff' => 21,
                'hotel_name' => 'Hotel Solene',
                'training_ground' => 'Training Ground A',
                'arrival_date_time' => '2026-04-15 20:50:00',
                'departure_date_time' => '2026-05-02 14:30:00',
                'head_of_delegation' => 'Carlos Rodriguez',
                'sc_liaison_name' => 'G. Morales',
                'sc_liaison_phone' => '+54 11 5555 1234',
                'bib_accent_color' => '#74ACDF',
                'notes' => null,
                'is_active' => true,
            ],
            [
                'code' => 'TOK',
                'team_name' => 'Tokai United',
                'country_id' => 'JPN',
                'flag' => '🇯🇵',
                'group_pool' => 'Group C',
                'classification_type_id' => $clubClassification->id,
                'party_size_total' => 40,
                'party_size_players' => 23,
                'party_size_staff' => 17,
                'hotel_name' => 'Hotel Verdi',
                'training_ground' => 'Training Ground B',
                'arrival_date_time' => '2026-04-16 22:10:00',
                'departure_date_time' => '2026-05-01 11:45:00',
                'head_of_delegation' => 'Hiroshi Tanaka',
                'sc_liaison_name' => 'J. Lindqvist',
                'sc_liaison_phone' => '+81 90 1234 5678',
                'bib_accent_color' => '#BC002D',
                'notes' => null,
                'is_active' => true,
            ],
        ];

        foreach ($teams as $team) {
            Team::create($team);
        }
    }
}
