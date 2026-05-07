<?php

namespace Database\Seeders;

use App\Models\CheckpointTemplate;
use App\Models\MovementTemplate;
use App\Models\MovementTemplateLeg;
use Illuminate\Database\Seeder;

class MatchDay4FullPlanSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Get checkpoint templates
        $arrivalTemplate = CheckpointTemplate::where('code', 'TPL-ARR')->first();
        $transferTemplate = CheckpointTemplate::where('code', 'TPL-TRF')->first();
        $trainingTemplate = CheckpointTemplate::where('code', 'TPL-TRN')->first();
        $matchTemplate = CheckpointTemplate::where('code', 'TPL-MTH')->first();
        $departureTemplate = CheckpointTemplate::where('code', 'TPL-DEP')->first();

        // Create the movement template
        $template = MovementTemplate::firstOrCreate(
            ['code' => 'MVT-MD4-FULL'],
            [
                'name' => 'Match Day 4 - Full Plan',
                'description' => 'Complete day itinerary: Team arrival, training session, match day operations, and departure',
                'scenario_type' => 'full_day',
                'is_active' => true,
            ]
        );

        // Define all legs for the full day
        $legs = [
            // Morning: Team Arrival
            [
                'order' => 1,
                'name' => 'Flight Arrival',
                'leg_type' => 'arrival',
                'from_location' => 'Arrival Gate',
                'to_location' => 'Arrivals Hall',
                'estimated_duration_minutes' => 45,
                'vehicle_type' => 'walk',
                'checkpoint_template_id' => $arrivalTemplate?->id,
            ],
            [
                'order' => 2,
                'name' => 'Airport to Hotel',
                'leg_type' => 'transfer',
                'from_location' => 'Airport',
                'to_location' => 'Team Hotel',
                'estimated_duration_minutes' => 45,
                'vehicle_type' => 'bus',
                'checkpoint_template_id' => $transferTemplate?->id,
            ],
            
            // Late Morning: Training Session
            [
                'order' => 3,
                'name' => 'Hotel to Training Ground',
                'leg_type' => 'transfer',
                'from_location' => 'Team Hotel',
                'to_location' => 'Training Ground',
                'estimated_duration_minutes' => 30,
                'vehicle_type' => 'bus',
                'checkpoint_template_id' => $transferTemplate?->id,
            ],
            [
                'order' => 4,
                'name' => 'Training Session',
                'leg_type' => 'training',
                'from_location' => 'Training Ground',
                'to_location' => 'Training Ground',
                'estimated_duration_minutes' => 90,
                'vehicle_type' => 'walk',
                'checkpoint_template_id' => $trainingTemplate?->id,
            ],
            [
                'order' => 5,
                'name' => 'Training Ground to Hotel',
                'leg_type' => 'transfer',
                'from_location' => 'Training Ground',
                'to_location' => 'Team Hotel',
                'estimated_duration_minutes' => 30,
                'vehicle_type' => 'bus',
                'checkpoint_template_id' => $transferTemplate?->id,
            ],
            
            // Afternoon: Rest & Preparation (No leg - implied)
            
            // Evening: Match Day
            [
                'order' => 6,
                'name' => 'Hotel to Stadium',
                'leg_type' => 'transfer',
                'from_location' => 'Team Hotel',
                'to_location' => 'Stadium Azure',
                'estimated_duration_minutes' => 25,
                'vehicle_type' => 'bus',
                'checkpoint_template_id' => $transferTemplate?->id,
            ],
            [
                'order' => 7,
                'name' => 'Match Day Operations',
                'leg_type' => 'match',
                'from_location' => 'Stadium Azure',
                'to_location' => 'Stadium Azure',
                'estimated_duration_minutes' => 180,
                'vehicle_type' => 'walk',
                'checkpoint_template_id' => $matchTemplate?->id,
            ],
            [
                'order' => 8,
                'name' => 'Stadium to Hotel',
                'leg_type' => 'transfer',
                'from_location' => 'Stadium Azure',
                'to_location' => 'Team Hotel',
                'estimated_duration_minutes' => 25,
                'vehicle_type' => 'bus',
                'checkpoint_template_id' => $transferTemplate?->id,
            ],
            
            // Late Night: Departure
            [
                'order' => 9,
                'name' => 'Hotel to Airport',
                'leg_type' => 'transfer',
                'from_location' => 'Team Hotel',
                'to_location' => 'Airport',
                'estimated_duration_minutes' => 45,
                'vehicle_type' => 'bus',
                'checkpoint_template_id' => $transferTemplate?->id,
            ],
            [
                'order' => 10,
                'name' => 'Flight Departure',
                'leg_type' => 'departure',
                'from_location' => 'Check-in',
                'to_location' => 'Departure Gate',
                'estimated_duration_minutes' => 90,
                'vehicle_type' => 'walk',
                'checkpoint_template_id' => $departureTemplate?->id,
            ],
        ];

        // Calculate total duration
        $totalDuration = array_sum(array_column($legs, 'estimated_duration_minutes'));
        
        // Update template with totals
        $template->update([
            'total_legs' => count($legs),
            'estimated_duration_minutes' => $totalDuration,
        ]);

        // Create legs
        foreach ($legs as $legData) {
            MovementTemplateLeg::updateOrCreate(
                [
                    'movement_template_id' => $template->id,
                    'order' => $legData['order'],
                ],
                $legData
            );
        }

        $this->command->info("Created: {$template->name}");
        $this->command->info("- Total legs: " . count($legs));
        $this->command->info("- Total duration: " . floor($totalDuration / 60) . "h " . ($totalDuration % 60) . "m");
        $this->command->info("Match Day 4 - Full Plan template created successfully!");
    }
}
