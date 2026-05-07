<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $vehicles = [
            [
                'code' => 'Coach 03',
                'vehicle_type' => 'Coach',
                'capacity' => 55,
                'fuel_level' => '88%',
                'plate_number' => 'ABC-1234',
                'status' => 'available',
                'is_active' => 1,
                'category' => 'Team',
            ],
            [
                'code' => 'Coach 07',
                'vehicle_type' => 'Coach',
                'capacity' => 55,
                'fuel_level' => '95%',
                'plate_number' => 'ABC-5678',
                'status' => 'on_job',
                'is_active' => 1,
                'category' => 'Team',
            ],
            [
                'code' => 'Coach 12',
                'vehicle_type' => 'Coach',
                'capacity' => 55,
                'fuel_level' => '92%',
                'plate_number' => 'ABC-9012',
                'status' => 'on_job',
                'is_active' => 1,
                'category' => 'Team',
            ],
            [
                'code' => 'Coach 14',
                'vehicle_type' => 'Coach',
                'capacity' => 55,
                'fuel_level' => '74%',
                'plate_number' => 'DEF-3456',
                'status' => 'on_job',
                'is_active' => 1,
                'category' => 'Official',
            ],
            [
                'code' => 'Van 04',
                'vehicle_type' => 'Minivan',
                'capacity' => 12,
                'fuel_level' => '81%',
                'plate_number' => 'GHI-7890',
                'status' => 'available',
                'is_active' => 1,
                'category' => 'Media',
            ],
            [
                'code' => 'Van 22',
                'vehicle_type' => 'Minivan',
                'capacity' => 12,
                'fuel_level' => '67%',
                'plate_number' => 'JKL-2345',
                'status' => 'on_job',
                'is_active' => 1,
                'category' => 'Media',
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::create($vehicle);
        }
    }
}
