<?php

namespace Database\Seeders;

use App\Models\Checkpoint;
use Illuminate\Database\Seeder;

class LogisticsCheckpointSeeder extends Seeder
{
    public function run(): void
    {
        $checkpoints = [
            [
                'code' => 'CK054',
                'name' => 'PMA Arrival',
                'category' => 'Logistics',
                'type' => 'arrival',
                'description' => 'arrival of PMA at airport',
                'capture_method' => 'manual',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CK056',
                'name' => 'Airport',
                'category' => 'Logistics',
                'type' => 'arrival',
                'description' => 'Arrival airport for the team',
                'capture_method' => 'manual',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CK057',
                'name' => 'Hotel (TBCH)',
                'category' => 'Logistics',
                'type' => 'arrival',
                'description' => 'Assigned hotel / team base camp hotel',
                'capture_method' => 'manual',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CK058',
                'name' => 'GWC Arrival at HIA POA Staging',
                'category' => 'Logistics',
                'type' => 'arrival',
                'description' => 'Time for GWC convoy to arrive at HIA POA staging area',
                'capture_method' => 'manual',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CK060',
                'name' => 'HIA POA Staging Convoy',
                'category' => 'Logistics',
                'type' => 'departure',
                'description' => 'Departure time of convoy from HIA POA staging',
                'capture_method' => 'manual',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CK062',
                'name' => 'Convoy Arrival at Airport',
                'category' => 'Logistics',
                'type' => 'arrival',
                'description' => 'Time convoy arrived at the airport',
                'capture_method' => 'gps',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CK063',
                'name' => 'QAS Handover Time',
                'category' => 'Logistics',
                'type' => 'handoff',
                'description' => 'Time of handover to QAS (Qatar Aviation Services)',
                'capture_method' => 'signature',
                'requires_photo' => false,
                'requires_signature' => true,
            ],
            [
                'code' => 'CK064',
                'name' => 'Baggage Manifest',
                'category' => 'Logistics',
                'type' => 'manual',
                'description' => 'Baggage pieces counted and recorded on the manifest',
                'capture_method' => 'manual',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CK066',
                'name' => 'Bag Loading',
                'category' => 'Logistics',
                'type' => 'boarding',
                'description' => 'Baggage loading',
                'capture_method' => 'manual',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CK067',
                'name' => 'Luggage Arrival at Destination',
                'category' => 'Logistics',
                'type' => 'arrival',
                'description' => 'Luggage arrival at the destination',
                'capture_method' => 'manual',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CK068',
                'name' => 'Bags Offload',
                'category' => 'Logistics',
                'type' => 'boarding',
                'description' => 'Bags offload',
                'capture_method' => 'manual',
                'requires_photo' => false,
                'requires_signature' => false,
            ],

        ];

        foreach ($checkpoints as $checkpoint) {
            Checkpoint::firstOrCreate(['code' => $checkpoint['code']], $checkpoint);
        }
    }
}
