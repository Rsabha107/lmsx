<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Driver;

class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $drivers = [
            [
                'name' => 'Sofia Reyes',
                'phone' => '+33 6 77 00 01',
                'license_number' => 'D+E',
                'status' => 'on_shift',
                'provider_id' => 1,
            ],
            [
                'name' => 'Luc Fontaine',
                'phone' => '+33 6 77 00 02',
                'license_number' => 'D',
                'status' => 'on_shift',
                'provider_id' => 1,
            ],
            [
                'name' => 'Peter Okonkwo',
                'phone' => '+33 6 77 00 03',
                'license_number' => 'D',
                'status' => 'on_shift',
                'provider_id' => 2,
            ],
            [
                'name' => 'Ahmed Bakr',
                'phone' => '+33 6 77 00 04',
                'license_number' => 'D+E',
                'status' => 'on_shift',
                'provider_id' => 2,
            ],
            [
                'name' => 'Kenji Petrov',
                'phone' => '+33 6 77 00 05',
                'license_number' => 'B',
                'status' => 'on_shift',
                'provider_id' => 3,
            ],
            [
                'name' => 'Thabo Mbeki',
                'phone' => '+33 6 77 00 06',
                'license_number' => 'B',
                'status' => 'on_shift',
                'provider_id' => 3,
            ],
            [
                'name' => 'Mia Lindgren',
                'phone' => '+33 6 77 00 07',
                'license_number' => 'D',
                'status' => 'rest',
                'provider_id' => 1,
            ],
        ];

        foreach ($drivers as $driver) {
            Driver::create($driver);
        }
    }
}
