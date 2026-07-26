<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            // Master data
            CountrySeeder::class,
            TeamClassificationSeeder::class,
            
            // Fleet & Contacts
            VehicleSeeder::class,
            FleetProviderSeeder::class,
            DriverSeeder::class,
            ContactSeeder::class,
            
            // Events (teams require an event to belong to)
            EventSeeder::class,

            // Teams
            TeamSeeder::class,
            U17EventTeamsSeeder::class,
            U17EventTeamStaysSeeder::class,
            
            // Airports
            AirportSeeder::class,
            U17EventTeamFlightsSeeder::class,

            // Matches
            U17EventMatchesSeeder::class,

            // Checkpoint system (NEW)
            CheckpointLibrarySeeder::class,
            CheckpointTemplateLibrarySeeder::class,
            MovementTemplateLibrarySeeder::class,
            LogisticsCheckpointSeeder::class,
            MatchDayCheckpointSeeder::class,
            
            // Sample data for demonstration
            SampleMovementsSeeder::class,
        ]);
    }
}
