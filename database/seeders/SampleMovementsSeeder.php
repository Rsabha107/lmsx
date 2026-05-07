<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Movement;
use App\Models\Team;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\CheckpointTemplate;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SampleMovementsSeeder extends Seeder
{
    /**
     * Seed sample plans and movements to demonstrate database integration.
     */
    public function run(): void
    {
        // Get checkpoint templates
        $arrivalTemplate = CheckpointTemplate::where('code', 'TPL-ARR')->first();
        $transferTemplate = CheckpointTemplate::where('code', 'TPL-TRF')->first();

        // Get some teams, vehicles, and drivers
        $teams = Team::active()->take(3)->get();
        $vehicles = Vehicle::take(3)->get();
        $drivers = Driver::take(3)->get();

        if ($teams->isEmpty() || $vehicles->isEmpty() || $drivers->isEmpty()) {
            $this->command->warn('Not enough teams, vehicles, or drivers to seed movements. Run other seeders first.');
            return;
        }

        // Create a sample plan for today
        $plan = Plan::firstOrCreate(
            ['code' => 'PLN-2026-0501-001'],
            [
                'name' => 'Match Day 4 – Active',
                'date' => Carbon::now(),
                'status' => 'active',
                'movements_count' => 0,
                'teams_count' => 0,
                'created_by' => 1,
            ]
        );

        // Create sample movements for each team
        foreach ($teams as $index => $team) {
            $baseTime = Carbon::now()->setTime(14 + ($index * 2), 0);
            $vehicle = $vehicles[$index % $vehicles->count()];
            $driver = $drivers[$index % $drivers->count()];

            // Arrival movement
            Movement::updateOrCreate(
                [
                    'code' => "MOV-{$plan->code}-{$team->code}-ARR",
                ],
                [
                    'plan_id' => $plan->id,
                    'team_id' => $team->id,
                    'checkpoint_template_id' => $arrivalTemplate?->id,
                    'kind' => 'arrival',
                    'from_location' => $team->originAirport?->code . ' Airport' ?? 'Airport',
                    'to_location' => $team->hotel_name ?? 'Team Hotel',
                    'window_start' => $baseTime->copy(),
                    'window_end' => $baseTime->copy()->addMinutes(45),
                    'actual_departure' => $index === 0 ? $baseTime->copy()->subMinutes(2) : null,
                    'actual_arrival' => null,
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'passengers' => $team->party_size_total ?? 30,
                    'status' => $index === 0 ? 'in-progress' : 'scheduled',
                    'delay_minutes' => null,
                    'source' => 'manual',
                    'job_id' => null,
                ]
            );

            // Transfer to training movement
            $trainingTime = $baseTime->copy()->addHours(1);
            Movement::updateOrCreate(
                [
                    'code' => "MOV-{$plan->code}-{$team->code}-TRN",
                ],
                [
                    'plan_id' => $plan->id,
                    'team_id' => $team->id,
                    'checkpoint_template_id' => $transferTemplate?->id,
                    'kind' => 'transfer',
                    'from_location' => $team->hotel_name ?? 'Team Hotel',
                    'to_location' => $team->training_ground ?? 'Training Ground',
                    'window_start' => $trainingTime->copy(),
                    'window_end' => $trainingTime->copy()->addMinutes(30),
                    'actual_departure' => null,
                    'actual_arrival' => null,
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'passengers' => $team->party_size_players ?? 25,
                    'status' => 'scheduled',
                    'delay_minutes' => null,
                    'source' => 'manual',
                    'job_id' => null,
                ]
            );

            // Return from training movement
            $returnTime = $trainingTime->copy()->addHours(2);
            Movement::updateOrCreate(
                [
                    'code' => "MOV-{$plan->code}-{$team->code}-RTN",
                ],
                [
                    'plan_id' => $plan->id,
                    'team_id' => $team->id,
                    'checkpoint_template_id' => $transferTemplate?->id,
                    'kind' => 'transfer',
                    'from_location' => $team->training_ground ?? 'Training Ground',
                    'to_location' => $team->hotel_name ?? 'Team Hotel',
                    'window_start' => $returnTime->copy(),
                    'window_end' => $returnTime->copy()->addMinutes(30),
                    'actual_departure' => null,
                    'actual_arrival' => null,
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'passengers' => $team->party_size_players ?? 25,
                    'status' => 'scheduled',
                    'delay_minutes' => null,
                    'source' => 'manual',
                    'job_id' => null,
                ]
            );
        }

        // Update plan counts
        $plan->update([
            'movements_count' => $plan->movements()->count(),
            'teams_count' => $plan->movements()->distinct('team_id')->count('team_id'),
        ]);

        $this->command->info("✓ Sample plan created: {$plan->name}");
        $this->command->info("✓ {$plan->movements_count} movements created for {$plan->teams_count} teams");
    }
}
