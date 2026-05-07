<?php

namespace Database\Seeders;

use App\Models\Checkpoint;
use App\Models\CheckpointTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegCheckpointTemplateSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Define checkpoints for each leg type
        $checkpointsData = [
            // Arrival checkpoints (flight arrival)
            ['name' => 'Gate Arrival', 'type' => 'arrival', 'capture_method' => 'photo'],
            ['name' => 'Disembark', 'type' => 'arrival', 'capture_method' => 'manual'],
            ['name' => 'Immigration', 'type' => 'arrival', 'capture_method' => 'photo'],
            ['name' => 'Baggage Claim', 'type' => 'arrival', 'capture_method' => 'photo'],
            ['name' => 'Customs Clearance', 'type' => 'arrival', 'capture_method' => 'manual'],
            ['name' => 'Meet & Greet', 'type' => 'handoff', 'capture_method' => 'signature'],
            
            // Transfer checkpoints
            ['name' => 'Boarding Point', 'type' => 'boarding', 'capture_method' => 'gps'],
            ['name' => 'Headcount Complete', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Vehicle Departure', 'type' => 'departure', 'capture_method' => 'photo'],
            ['name' => 'En Route Update', 'type' => 'dispatch', 'capture_method' => 'gps'],
            ['name' => 'Destination Arrival', 'type' => 'arrival', 'capture_method' => 'photo'],
            ['name' => 'Passenger Disembark', 'type' => 'handoff', 'capture_method' => 'manual'],
            
            // Training checkpoints
            ['name' => 'Training Facility Arrival', 'type' => 'arrival', 'capture_method' => 'photo'],
            ['name' => 'Check-in Complete', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Equipment Ready', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Training Start', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Training End', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Training Facility Departure', 'type' => 'departure', 'capture_method' => 'photo'],
            
            // Match checkpoints
            ['name' => 'Stadium Arrival', 'type' => 'arrival', 'capture_method' => 'photo'],
            ['name' => 'Security Check Complete', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Locker Room Entry', 'type' => 'arrival', 'capture_method' => 'manual'],
            ['name' => 'Pre-Match Briefing', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Warm-up Start', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Match Kickoff', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Half-time', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Match End', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Post-Match Activities', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Stadium Departure', 'type' => 'departure', 'capture_method' => 'photo'],
            
            // Departure checkpoints (flight departure)
            ['name' => 'Hotel Checkout', 'type' => 'departure', 'capture_method' => 'manual'],
            ['name' => 'Airport Drop-off', 'type' => 'arrival', 'capture_method' => 'photo'],
            ['name' => 'Check-in Counter', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Security Screening', 'type' => 'dispatch', 'capture_method' => 'manual'],
            ['name' => 'Departure Gate', 'type' => 'boarding', 'capture_method' => 'photo'],
            ['name' => 'Boarding Complete', 'type' => 'boarding', 'capture_method' => 'manual'],
            ['name' => 'Flight Departure', 'type' => 'departure', 'capture_method' => 'manual'],
        ];

        // Create or find checkpoints
        $checkpoints = [];
        $nextCheckpointNumber = Checkpoint::count() + 1;
        
        foreach ($checkpointsData as $data) {
            $checkpoint = Checkpoint::firstOrCreate(
                ['name' => $data['name']],
                [
                    'code' => 'CK' . $nextCheckpointNumber++,
                    'type' => $data['type'],
                    'capture_method' => $data['capture_method'],
                    'requires_photo' => $data['capture_method'] === 'photo',
                    'requires_signature' => $data['capture_method'] === 'signature',
                    'is_active' => true,
                ]
            );
            $checkpoints[$data['name']] = $checkpoint;
        }

        // Define checkpoint templates for each leg type
        $templates = [
            [
                'code' => 'TPL-ARR',
                'name' => 'Flight Arrival Protocol',
                'description' => 'Complete checkpoint sequence for team arrival at airport',
                'checkpoints' => [
                    'Gate Arrival',
                    'Disembark',
                    'Immigration',
                    'Baggage Claim',
                    'Customs Clearance',
                    'Meet & Greet',
                ],
            ],
            [
                'code' => 'TPL-TRF',
                'name' => 'Ground Transfer Standard',
                'description' => 'Standard checkpoint sequence for ground transportation',
                'checkpoints' => [
                    'Boarding Point',
                    'Headcount Complete',
                    'Vehicle Departure',
                    'En Route Update',
                    'Destination Arrival',
                    'Passenger Disembark',
                ],
            ],
            [
                'code' => 'TPL-TRN',
                'name' => 'Training Session Protocol',
                'description' => 'Checkpoint sequence for training ground activities',
                'checkpoints' => [
                    'Training Facility Arrival',
                    'Check-in Complete',
                    'Equipment Ready',
                    'Training Start',
                    'Training End',
                    'Training Facility Departure',
                ],
            ],
            [
                'code' => 'TPL-MTH',
                'name' => 'Match Day Protocol',
                'description' => 'Complete checkpoint sequence for match day operations',
                'checkpoints' => [
                    'Stadium Arrival',
                    'Security Check Complete',
                    'Locker Room Entry',
                    'Pre-Match Briefing',
                    'Warm-up Start',
                    'Match Kickoff',
                    'Half-time',
                    'Match End',
                    'Post-Match Activities',
                    'Stadium Departure',
                ],
            ],
            [
                'code' => 'TPL-DEP',
                'name' => 'Flight Departure Protocol',
                'description' => 'Complete checkpoint sequence for team departure',
                'checkpoints' => [
                    'Hotel Checkout',
                    'Airport Drop-off',
                    'Check-in Counter',
                    'Security Screening',
                    'Departure Gate',
                    'Boarding Complete',
                    'Flight Departure',
                ],
            ],
        ];

        // Create checkpoint templates and associate checkpoints
        foreach ($templates as $templateData) {
            $template = CheckpointTemplate::firstOrCreate(
                ['code' => $templateData['code']],
                [
                    'name' => $templateData['name'],
                    'description' => $templateData['description'],
                    'is_active' => true,
                ]
            );

            // Attach checkpoints in order
            $order = 1;
            foreach ($templateData['checkpoints'] as $checkpointName) {
                if (isset($checkpoints[$checkpointName])) {
                    // Check if pivot already exists
                    $exists = DB::table('checkpoint_checkpoint_template')
                        ->where('checkpoint_template_id', $template->id)
                        ->where('checkpoint_id', $checkpoints[$checkpointName]->id)
                        ->exists();

                    if (!$exists) {
                        DB::table('checkpoint_checkpoint_template')->insert([
                            'checkpoint_template_id' => $template->id,
                            'checkpoint_id' => $checkpoints[$checkpointName]->id,
                            'order' => $order++,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            $this->command->info("Created template: {$template->name} with " . count($templateData['checkpoints']) . " checkpoints");
        }

        $this->command->info('Leg checkpoint templates seeded successfully!');
    }
}

