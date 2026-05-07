<?php

namespace Database\Seeders;

use App\Models\CheckpointTemplate;
use App\Models\MovementTemplate;
use App\Models\MovementTemplateLeg;
use Illuminate\Database\Seeder;

class MovementTemplateSeeder extends Seeder
{
    /**
     * Seed movement templates with their legs.
     */
    public function run(): void
    {
        // 1. Match Day - Standard (4 legs)
        $matchDay = MovementTemplate::create([
            'code' => 'MVT-MATCH-STD',
            'name' => 'Match Day - Standard',
            'description' => 'Standard match day transportation protocol for team movements',
            'scenario_type' => 'match_day',
            'total_legs' => 4,
            'estimated_duration_minutes' => 225, // ~3h 45m
            'is_active' => true,
        ]);

        $stadiumTransfer = CheckpointTemplate::where('code', 'TPL-TRF-STADIUM')->first();
        $matchReturn = CheckpointTemplate::where('code', 'TPL-DEP-MATCH')->first();
        $quickTransfer = CheckpointTemplate::where('code', 'TPL-TRF-QUICK')->first();

        MovementTemplateLeg::create([
            'movement_template_id' => $matchDay->id,
            'checkpoint_template_id' => $stadiumTransfer->id,
            'order' => 1,
            'name' => 'Pre-match transfer to stadium',
            'leg_type' => 'transfer',
            'from_location' => 'Team Hotel',
            'to_location' => 'Stadium',
            'estimated_duration_minutes' => 45,
            'vehicle_type' => 'coach',
            'estimated_passengers' => 35,
        ]);

        MovementTemplateLeg::create([
            'movement_template_id' => $matchDay->id,
            'checkpoint_template_id' => $matchReturn->id,
            'order' => 2,
            'name' => 'Post-match return to hotel',
            'leg_type' => 'transfer',
            'from_location' => 'Stadium',
            'to_location' => 'Team Hotel',
            'estimated_duration_minutes' => 50,
            'vehicle_type' => 'coach',
            'estimated_passengers' => 35,
        ]);

        MovementTemplateLeg::create([
            'movement_template_id' => $matchDay->id,
            'checkpoint_template_id' => $quickTransfer->id,
            'order' => 3,
            'name' => 'Next morning media center visit',
            'leg_type' => 'transfer',
            'from_location' => 'Team Hotel',
            'to_location' => 'Media Center',
            'estimated_duration_minutes' => 30,
            'vehicle_type' => 'van',
            'estimated_passengers' => 8,
        ]);

        MovementTemplateLeg::create([
            'movement_template_id' => $matchDay->id,
            'checkpoint_template_id' => $quickTransfer->id,
            'order' => 4,
            'name' => 'Media center return',
            'leg_type' => 'transfer',
            'from_location' => 'Media Center',
            'to_location' => 'Team Hotel',
            'estimated_duration_minutes' => 25,
            'vehicle_type' => 'van',
            'estimated_passengers' => 8,
        ]);

        // 2. Training Day (2 legs)
        $trainingDay = MovementTemplate::create([
            'code' => 'MVT-TRAIN-STD',
            'name' => 'Training Session',
            'description' => 'Daily training ground transportation routine',
            'scenario_type' => 'training_day',
            'total_legs' => 2,
            'estimated_duration_minutes' => 80,
            'is_active' => true,
        ]);

        $trainingTransfer = CheckpointTemplate::where('code', 'TPL-TRF-TRAINING')->first();

        MovementTemplateLeg::create([
            'movement_template_id' => $trainingDay->id,
            'checkpoint_template_id' => $trainingTransfer->id,
            'order' => 1,
            'name' => 'Hotel to training ground',
            'leg_type' => 'training',
            'from_location' => 'Team Hotel',
            'to_location' => 'Training Ground',
            'estimated_duration_minutes' => 40,
            'vehicle_type' => 'coach',
            'estimated_passengers' => 35,
        ]);

        MovementTemplateLeg::create([
            'movement_template_id' => $trainingDay->id,
            'checkpoint_template_id' => $trainingTransfer->id,
            'order' => 2,
            'name' => 'Training ground to hotel',
            'leg_type' => 'training',
            'from_location' => 'Training Ground',
            'to_location' => 'Team Hotel',
            'estimated_duration_minutes' => 40,
            'vehicle_type' => 'coach',
            'estimated_passengers' => 35,
        ]);

        // 3. Arrival Day (3 legs)
        $arrivalDay = MovementTemplate::create([
            'code' => 'MVT-ARR-STD',
            'name' => 'Team Arrival Day',
            'description' => 'Team arrival at destination city with airport pickup and hotel check-in',
            'scenario_type' => 'arrival_day',
            'total_legs' => 3,
            'estimated_duration_minutes' => 155,
            'is_active' => true,
        ]);

        $airportArrival = CheckpointTemplate::where('code', 'TPL-ARR-AIRPORT')->first();

        MovementTemplateLeg::create([
            'movement_template_id' => $arrivalDay->id,
            'checkpoint_template_id' => $airportArrival->id,
            'order' => 1,
            'name' => 'Airport pickup and hotel transfer',
            'leg_type' => 'arrival',
            'from_location' => 'Airport',
            'to_location' => 'Team Hotel',
            'estimated_duration_minutes' => 75,
            'vehicle_type' => 'coach',
            'estimated_passengers' => 35,
        ]);

        MovementTemplateLeg::create([
            'movement_template_id' => $arrivalDay->id,
            'checkpoint_template_id' => $trainingTransfer->id,
            'order' => 2,
            'name' => 'Light training session',
            'leg_type' => 'training',
            'from_location' => 'Team Hotel',
            'to_location' => 'Training Ground',
            'estimated_duration_minutes' => 40,
            'vehicle_type' => 'coach',
            'estimated_passengers' => 35,
        ]);

        MovementTemplateLeg::create([
            'movement_template_id' => $arrivalDay->id,
            'checkpoint_template_id' => $trainingTransfer->id,
            'order' => 3,
            'name' => 'Return to hotel',
            'leg_type' => 'training',
            'from_location' => 'Training Ground',
            'to_location' => 'Team Hotel',
            'estimated_duration_minutes' => 40,
            'vehicle_type' => 'coach',
            'estimated_passengers' => 35,
        ]);
    }
}
