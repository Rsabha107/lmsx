<?php

namespace Database\Seeders;

use App\Models\Checkpoint;
use Illuminate\Database\Seeder;

class CheckpointSeeder extends Seeder
{
    /**
     * Seed the global checkpoint library.
     */
    public function run(): void
    {
        $checkpoints = [
            // Dispatch & Assignment
            [
                'code' => 'CKP-DISPATCH',
                'name' => 'Vehicle Dispatch',
                'type' => 'dispatch',
                'description' => 'Vehicle dispatched from depot/pool',
                'capture_method' => 'auto',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CKP-DRIVER-ASSIGN',
                'name' => 'Driver Assignment Confirmed',
                'type' => 'dispatch',
                'description' => 'Driver confirmed and en route',
                'capture_method' => 'auto',
                'requires_photo' => false,
                'requires_signature' => false,
            ],

            // Arrival at Origin
            [
                'code' => 'CKP-ARRIVE-ORIGIN',
                'name' => 'Arrived at Origin',
                'type' => 'arrival',
                'description' => 'Vehicle arrived at pickup location',
                'capture_method' => 'gps',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CKP-ARRIVE-AIRPORT',
                'name' => 'Arrived at Airport',
                'type' => 'arrival',
                'description' => 'Vehicle arrived at airport terminal',
                'capture_method' => 'gps',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CKP-FLIGHT-LANDED',
                'name' => 'Flight Landed',
                'type' => 'arrival',
                'description' => 'Incoming flight has landed (live feed)',
                'capture_method' => 'auto',
                'requires_photo' => false,
                'requires_signature' => false,
            ],

            // Boarding
            [
                'code' => 'CKP-TEAM-ONBOARD',
                'name' => 'Team on Board',
                'type' => 'boarding',
                'description' => 'All passengers boarded and accounted for',
                'capture_method' => 'photo',
                'requires_photo' => true,
                'requires_signature' => false,
            ],
            [
                'code' => 'CKP-BAGS-LOADED',
                'name' => 'Bags Loaded',
                'type' => 'boarding',
                'description' => 'All luggage loaded into vehicle',
                'capture_method' => 'signature',
                'requires_photo' => false,
                'requires_signature' => true,
            ],
            [
                'code' => 'CKP-EQUIPMENT-LOADED',
                'name' => 'Equipment Loaded',
                'type' => 'boarding',
                'description' => 'Sports equipment and gear secured',
                'capture_method' => 'manual',
                'requires_photo' => false,
                'requires_signature' => false,
            ],

            // Departure
            [
                'code' => 'CKP-DEPART-ORIGIN',
                'name' => 'Depart Origin',
                'type' => 'departure',
                'description' => 'Vehicle departed from pickup location',
                'capture_method' => 'gps',
                'requires_photo' => false,
                'requires_signature' => false,
            ],

            // Arrival at Destination
            [
                'code' => 'CKP-ARRIVE-DEST',
                'name' => 'Arrive at Destination',
                'type' => 'arrival',
                'description' => 'Vehicle arrived at drop-off location',
                'capture_method' => 'gps',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CKP-ARRIVE-HOTEL',
                'name' => 'Arrive at Hotel',
                'type' => 'arrival',
                'description' => 'Vehicle arrived at team hotel',
                'capture_method' => 'gps',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
            [
                'code' => 'CKP-ARRIVE-STADIUM',
                'name' => 'Arrive at Stadium',
                'type' => 'arrival',
                'description' => 'Vehicle arrived at stadium/venue',
                'capture_method' => 'gps',
                'requires_photo' => false,
                'requires_signature' => false,
            ],

            // Handoff
            [
                'code' => 'CKP-HANDOFF-COMPLETE',
                'name' => 'Handoff Complete',
                'type' => 'handoff',
                'description' => 'Team handed off to venue/hotel staff',
                'capture_method' => 'signature',
                'requires_photo' => true,
                'requires_signature' => true,
            ],
            [
                'code' => 'CKP-LIAISON-CONFIRM',
                'name' => 'Liaison Confirmation',
                'type' => 'handoff',
                'description' => 'Team liaison confirms receipt',
                'capture_method' => 'signature',
                'requires_photo' => false,
                'requires_signature' => true,
            ],

            // Security & Safety
            [
                'code' => 'CKP-SECURITY-CLEAR',
                'name' => 'Security Check Cleared',
                'type' => 'manual',
                'description' => 'Security clearance obtained',
                'capture_method' => 'manual',
                'requires_photo' => false,
                'requires_signature' => false,
            ],
        ];

        foreach ($checkpoints as $checkpoint) {
            Checkpoint::create($checkpoint);
        }
    }
}
