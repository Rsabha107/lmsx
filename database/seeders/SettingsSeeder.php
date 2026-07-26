<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Global default movement offsets (in minutes, negative = before reference time)
        $defaults = [
            [
                'key' => 'movement_offset.arrival',
                'value' => '-180',
                'description' => 'Minutes before flight arrival to start movement (default: 3 hours)',
            ],
            [
                'key' => 'movement_offset.departure',
                'value' => '-30',
                'description' => 'Minutes before flight departure to start movement (default: 30 minutes)',
            ],
            [
                'key' => 'movement_offset.match',
                'value' => '-120',
                'description' => 'Minutes before match kick-off to start movement (default: 2 hours)',
            ],
            [
                'key' => 'movement_offset.transfer',
                'value' => '-180',
                'description' => 'Minutes before reference time for transfer movements (default: 3 hours)',
            ],
            [
                'key' => 'movement_offset.training',
                'value' => '0',
                'description' => 'Minutes offset for training movements (default: 0)',
            ],
            [
                'key' => 'movement_offset.daily_ops',
                'value' => '0',
                'description' => 'Minutes offset for daily operations movements (default: 0)',
            ],
        ];

        foreach ($defaults as $setting) {
            Setting::updateOrCreate(
                [
                    'key' => $setting['key'],
                    'scope' => Setting::SCOPE_GLOBAL,
                    'scope_id' => null,
                    'checkpoint_id' => null,
                ],
                [
                    'value' => $setting['value'],
                    'description' => $setting['description'],
                ]
            );
        }

        $this->command->info('Global settings seeded successfully.');
    }
}
