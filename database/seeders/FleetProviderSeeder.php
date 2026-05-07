<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\FleetProvider;

class FleetProviderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $providers = [
            [
                'code' => 'P001',
                'name' => 'Nord Coaches',
                'contact_person' => 'J. Kovac',
                'phone' => '+33 6 78 90 01',
                'email' => 'j.kovac@nordcoaches.com',
                'total_vehicles' => 8,
                'total_drivers' => 12,
                'rating' => 4.8,
                'status' => 'active',
                'notes' => '15 min response, specialized in coach operations',
                'is_active' => 1,
            ],
            [
                'code' => 'P002',
                'name' => 'SudTrans',
                'contact_person' => 'M. Dupont',
                'phone' => '+33 6 78 90 02',
                'email' => 'm.dupont@sudtrans.fr',
                'total_vehicles' => 4,
                'total_drivers' => 6,
                'rating' => 4.5,
                'status' => 'active',
                'notes' => '20 min response, reliable coach operator',
                'is_active' => 1,
            ],
            [
                'code' => 'P003',
                'name' => 'Atlas Fleet',
                'contact_person' => 'R. Saad',
                'phone' => '+33 6 78 90 03',
                'email' => 'r.saad@atlasfleet.com',
                'total_vehicles' => 6,
                'total_drivers' => 8,
                'rating' => 4.7,
                'status' => 'active',
                'notes' => '10 min response, minivan fleet specialist',
                'is_active' => 1,
            ],
            [
                'code' => 'P004',
                'name' => 'VIP Transfer',
                'contact_person' => 'C. Leclerc',
                'phone' => '+33 6 78 90 04',
                'email' => 'c.leclerc@viptransfer.com',
                'total_vehicles' => 3,
                'total_drivers' => 5,
                'rating' => 4.9,
                'status' => 'standby',
                'notes' => '30 min response, luxury car service',
                'is_active' => 0,
            ],
        ];

        foreach ($providers as $provider) {
            FleetProvider::create($provider);
        }
    }
}
