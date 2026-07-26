<?php

namespace Database\Seeders;

use App\Models\Event;
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
        $event = Event::where('short_name', 'FU17WC')
            ->orWhere('name', 'FIFA U-17 WORLD CUP QATAR 2026')
            ->first();

        if (! $event) {
            $this->command?->warn('FIFA U-17 World Cup Qatar 2026 event not found, skipping.');

            return;
        }

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
                'head_of_delegation' => 'Pierre Dubois',
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
                'head_of_delegation' => 'Lars Hansen',
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
                'head_of_delegation' => 'Ahmed Al-Mansouri',
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
                'head_of_delegation' => 'Carlos Rodriguez',
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
                'head_of_delegation' => 'Hiroshi Tanaka',
                'bib_accent_color' => '#BC002D',
                'notes' => null,
                'is_active' => true,
            ],
        ];

        foreach ($teams as $team) {
            Team::updateOrCreate(
                ['code' => $team['code'], 'event_id' => $event->id],
                $team
            );
        }
    }
}
