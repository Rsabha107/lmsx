<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AirportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $airports = [
            // Major European Airports
            ['code' => 'LHR', 'name' => 'London Heathrow Airport', 'city' => 'London', 'country' => 'United Kingdom'],
            ['code' => 'CDG', 'name' => 'Charles de Gaulle Airport', 'city' => 'Paris', 'country' => 'France'],
            ['code' => 'FRA', 'name' => 'Frankfurt Airport', 'city' => 'Frankfurt', 'country' => 'Germany'],
            ['code' => 'AMS', 'name' => 'Amsterdam Schiphol Airport', 'city' => 'Amsterdam', 'country' => 'Netherlands'],
            ['code' => 'MAD', 'name' => 'Adolfo Suárez Madrid-Barajas Airport', 'city' => 'Madrid', 'country' => 'Spain'],
            ['code' => 'BCN', 'name' => 'Barcelona-El Prat Airport', 'city' => 'Barcelona', 'country' => 'Spain'],
            ['code' => 'FCO', 'name' => 'Leonardo da Vinci-Fiumicino Airport', 'city' => 'Rome', 'country' => 'Italy'],
            ['code' => 'MUC', 'name' => 'Munich Airport', 'city' => 'Munich', 'country' => 'Germany'],
            ['code' => 'LGW', 'name' => 'London Gatwick Airport', 'city' => 'London', 'country' => 'United Kingdom'],
            ['code' => 'ZRH', 'name' => 'Zurich Airport', 'city' => 'Zurich', 'country' => 'Switzerland'],
            
            // North American Airports
            ['code' => 'JFK', 'name' => 'John F. Kennedy International Airport', 'city' => 'New York', 'country' => 'United States'],
            ['code' => 'LAX', 'name' => 'Los Angeles International Airport', 'city' => 'Los Angeles', 'country' => 'United States'],
            ['code' => 'ORD', 'name' => 'O\'Hare International Airport', 'city' => 'Chicago', 'country' => 'United States'],
            ['code' => 'ATL', 'name' => 'Hartsfield-Jackson Atlanta International Airport', 'city' => 'Atlanta', 'country' => 'United States'],
            ['code' => 'YYZ', 'name' => 'Toronto Pearson International Airport', 'city' => 'Toronto', 'country' => 'Canada'],
            ['code' => 'DFW', 'name' => 'Dallas/Fort Worth International Airport', 'city' => 'Dallas', 'country' => 'United States'],
            ['code' => 'MIA', 'name' => 'Miami International Airport', 'city' => 'Miami', 'country' => 'United States'],
            ['code' => 'EWR', 'name' => 'Newark Liberty International Airport', 'city' => 'Newark', 'country' => 'United States'],
            
            // Asian Airports
            ['code' => 'DXB', 'name' => 'Dubai International Airport', 'city' => 'Dubai', 'country' => 'United Arab Emirates'],
            ['code' => 'HND', 'name' => 'Tokyo Haneda Airport', 'city' => 'Tokyo', 'country' => 'Japan'],
            ['code' => 'NRT', 'name' => 'Narita International Airport', 'city' => 'Tokyo', 'country' => 'Japan'],
            ['code' => 'SIN', 'name' => 'Singapore Changi Airport', 'city' => 'Singapore', 'country' => 'Singapore'],
            ['code' => 'HKG', 'name' => 'Hong Kong International Airport', 'city' => 'Hong Kong', 'country' => 'Hong Kong'],
            ['code' => 'ICN', 'name' => 'Incheon International Airport', 'city' => 'Seoul', 'country' => 'South Korea'],
            ['code' => 'PEK', 'name' => 'Beijing Capital International Airport', 'city' => 'Beijing', 'country' => 'China'],
            ['code' => 'PVG', 'name' => 'Shanghai Pudong International Airport', 'city' => 'Shanghai', 'country' => 'China'],
            ['code' => 'BKK', 'name' => 'Suvarnabhumi Airport', 'city' => 'Bangkok', 'country' => 'Thailand'],
            ['code' => 'KUL', 'name' => 'Kuala Lumpur International Airport', 'city' => 'Kuala Lumpur', 'country' => 'Malaysia'],
            
            // Middle Eastern Airports
            ['code' => 'DOH', 'name' => 'Hamad International Airport', 'city' => 'Doha', 'country' => 'Qatar'],
            ['code' => 'AUH', 'name' => 'Abu Dhabi International Airport', 'city' => 'Abu Dhabi', 'country' => 'United Arab Emirates'],
            ['code' => 'IST', 'name' => 'Istanbul Airport', 'city' => 'Istanbul', 'country' => 'Turkey'],
            
            // Oceania Airports
            ['code' => 'SYD', 'name' => 'Sydney Kingsford Smith Airport', 'city' => 'Sydney', 'country' => 'Australia'],
            ['code' => 'MEL', 'name' => 'Melbourne Airport', 'city' => 'Melbourne', 'country' => 'Australia'],
            ['code' => 'AKL', 'name' => 'Auckland Airport', 'city' => 'Auckland', 'country' => 'New Zealand'],
            
            // African Airports
            ['code' => 'JNB', 'name' => 'O.R. Tambo International Airport', 'city' => 'Johannesburg', 'country' => 'South Africa'],
            ['code' => 'CAI', 'name' => 'Cairo International Airport', 'city' => 'Cairo', 'country' => 'Egypt'],
            ['code' => 'CPT', 'name' => 'Cape Town International Airport', 'city' => 'Cape Town', 'country' => 'South Africa'],
            
            // South American Airports
            ['code' => 'GRU', 'name' => 'São Paulo-Guarulhos International Airport', 'city' => 'São Paulo', 'country' => 'Brazil'],
            ['code' => 'EZE', 'name' => 'Ministro Pistarini International Airport', 'city' => 'Buenos Aires', 'country' => 'Argentina'],
            ['code' => 'BOG', 'name' => 'El Dorado International Airport', 'city' => 'Bogotá', 'country' => 'Colombia'],
            ['code' => 'LIM', 'name' => 'Jorge Chávez International Airport', 'city' => 'Lima', 'country' => 'Peru'],
        ];

        foreach ($airports as $airport) {
            DB::table('airports')->updateOrInsert(
                ['code' => $airport['code']],
                $airport
            );
        }
    }
}
