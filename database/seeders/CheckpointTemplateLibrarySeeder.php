<?php

namespace Database\Seeders;

use App\Models\Checkpoint;
use App\Models\CheckpointTemplate;
use Illuminate\Database\Seeder;

class CheckpointTemplateSeeder extends Seeder
{
    /**
     * Seed checkpoint templates with their checkpoint sequences.
     */
    public function run(): void
    {
        // 1. Airport Arrival Template (7 checkpoints)
        $airportArrival = CheckpointTemplate::create([
            'code' => 'TPL-ARR-AIRPORT',
            'name' => 'Airport Arrival',
            'movement_type' => 'arrival',
            'description' => 'Standard protocol for picking up teams from airport',
            'estimated_duration_minutes' => 75,
            'is_active' => true,
        ]);

        $airportArrival->checkpoints()->attach([
            Checkpoint::where('code', 'CKP-DISPATCH')->first()->id => ['order' => 1, 'is_required' => true, 'estimated_minutes' => 5],
            Checkpoint::where('code', 'CKP-ARRIVE-AIRPORT')->first()->id => ['order' => 2, 'is_required' => true, 'estimated_minutes' => 5],
            Checkpoint::where('code', 'CKP-FLIGHT-LANDED')->first()->id => ['order' => 3, 'is_required' => true, 'estimated_minutes' => 10],
            Checkpoint::where('code', 'CKP-TEAM-ONBOARD')->first()->id => ['order' => 4, 'is_required' => true, 'estimated_minutes' => 8],
            Checkpoint::where('code', 'CKP-BAGS-LOADED')->first()->id => ['order' => 5, 'is_required' => true, 'estimated_minutes' => 10],
            Checkpoint::where('code', 'CKP-DEPART-ORIGIN')->first()->id => ['order' => 6, 'is_required' => true, 'estimated_minutes' => 2],
            Checkpoint::where('code', 'CKP-ARRIVE-HOTEL')->first()->id => ['order' => 7, 'is_required' => true, 'estimated_minutes' => 35],
        ]);

        // 2. Hotel to Stadium Transfer (5 checkpoints)
        $stadiumTransfer = CheckpointTemplate::create([
            'code' => 'TPL-TRF-STADIUM',
            'name' => 'Stadium Transfer',
            'movement_type' => 'transfer',
            'description' => 'Standard transfer from hotel to stadium',
            'estimated_duration_minutes' => 45,
            'is_active' => true,
        ]);

        $stadiumTransfer->checkpoints()->attach([
            Checkpoint::where('code', 'CKP-DISPATCH')->first()->id => ['order' => 1, 'is_required' => true, 'estimated_minutes' => 5],
            Checkpoint::where('code', 'CKP-ARRIVE-ORIGIN')->first()->id => ['order' => 2, 'is_required' => true, 'estimated_minutes' => 5],
            Checkpoint::where('code', 'CKP-TEAM-ONBOARD')->first()->id => ['order' => 3, 'is_required' => true, 'estimated_minutes' => 5],
            Checkpoint::where('code', 'CKP-DEPART-ORIGIN')->first()->id => ['order' => 4, 'is_required' => true, 'estimated_minutes' => 2],
            Checkpoint::where('code', 'CKP-ARRIVE-STADIUM')->first()->id => ['order' => 5, 'is_required' => true, 'estimated_minutes' => 28],
        ]);

        // 3. Training Session Transfer (5 checkpoints)
        $trainingTransfer = CheckpointTemplate::create([
            'code' => 'TPL-TRF-TRAINING',
            'name' => 'Training Ground Transfer',
            'movement_type' => 'training',
            'description' => 'Transfer to training facilities',
            'estimated_duration_minutes' => 40,
            'is_active' => true,
        ]);

        $trainingTransfer->checkpoints()->attach([
            Checkpoint::where('code', 'CKP-DISPATCH')->first()->id => ['order' => 1, 'is_required' => true, 'estimated_minutes' => 5],
            Checkpoint::where('code', 'CKP-ARRIVE-ORIGIN')->first()->id => ['order' => 2, 'is_required' => true, 'estimated_minutes' => 5],
            Checkpoint::where('code', 'CKP-TEAM-ONBOARD')->first()->id => ['order' => 3, 'is_required' => true, 'estimated_minutes' => 5],
            Checkpoint::where('code', 'CKP-DEPART-ORIGIN')->first()->id => ['order' => 4, 'is_required' => true, 'estimated_minutes' => 2],
            Checkpoint::where('code', 'CKP-ARRIVE-DEST')->first()->id => ['order' => 5, 'is_required' => true, 'estimated_minutes' => 23],
        ]);

        // 4. Match Day Return (6 checkpoints) - with handoff
        $matchReturn = CheckpointTemplate::create([
            'code' => 'TPL-DEP-MATCH',
            'name' => 'Match Day Return',
            'movement_type' => 'departure',
            'description' => 'Post-match return to hotel with handoff',
            'estimated_duration_minutes' => 50,
            'is_active' => true,
        ]);

        $matchReturn->checkpoints()->attach([
            Checkpoint::where('code', 'CKP-DISPATCH')->first()->id => ['order' => 1, 'is_required' => true, 'estimated_minutes' => 5],
            Checkpoint::where('code', 'CKP-ARRIVE-ORIGIN')->first()->id => ['order' => 2, 'is_required' => true, 'estimated_minutes' => 5],
            Checkpoint::where('code', 'CKP-TEAM-ONBOARD')->first()->id => ['order' => 3, 'is_required' => true, 'estimated_minutes' => 8],
            Checkpoint::where('code', 'CKP-BAGS-LOADED')->first()->id => ['order' => 4, 'is_required' => true, 'estimated_minutes' => 7],
            Checkpoint::where('code', 'CKP-DEPART-ORIGIN')->first()->id => ['order' => 5, 'is_required' => true, 'estimated_minutes' => 2],
            Checkpoint::where('code', 'CKP-HANDOFF-COMPLETE')->first()->id => ['order' => 6, 'is_required' => true, 'estimated_minutes' => 23],
        ]);

        // 5. Quick Transfer (3 checkpoints) - simplified
        $quickTransfer = CheckpointTemplate::create([
            'code' => 'TPL-TRF-QUICK',
            'name' => 'Quick Transfer',
            'movement_type' => 'transfer',
            'description' => 'Simplified transfer for short distances',
            'estimated_duration_minutes' => 25,
            'is_active' => true,
        ]);

        $quickTransfer->checkpoints()->attach([
            Checkpoint::where('code', 'CKP-DISPATCH')->first()->id => ['order' => 1, 'is_required' => true, 'estimated_minutes' => 5],
            Checkpoint::where('code', 'CKP-TEAM-ONBOARD')->first()->id => ['order' => 2, 'is_required' => true, 'estimated_minutes' => 5],
            Checkpoint::where('code', 'CKP-ARRIVE-DEST')->first()->id => ['order' => 3, 'is_required' => true, 'estimated_minutes' => 15],
        ]);
    }
}
