<?php

namespace App\Services;

use App\Models\CheckpointTemplate;
use App\Models\Driver;
use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\MovementTemplate;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Service for generating logistics jobs from planned movements.
 * 
 * This service handles:
 * - Generating jobs from movements
 * - Snapshotting checkpoint sequences from templates
 * - Assigning vehicles and drivers
 */
class JobGenerationService
{
    /**
     * Generate a job from a movement.
     * 
     * This snapshots the checkpoint template at the time of generation,
     * ensuring historical integrity.
     */
    public function generateJobFromMovement(Movement $movement, ?array $options = []): JobOperation
    {
        return DB::transaction(function () use ($movement, $options) {
            // Create the job
            $job = JobOperation::create([
                'job_id' => $this->generateJobId(),
                'event_id' => $movement->event_id,
                'movement_id' => $movement->id,
                'plan_id' => $movement->plan_id,
                'team_id' => $movement->team_id,
                'supervisor_id' => $movement->field_supervisor_id ?? $options['supervisor_id'] ?? null,
                'driver_id' => $movement->driver_id,
                'vehicle_id' => $movement->vehicle_id,
                'status' => 'pending',
            ]);

            // Snapshot checkpoints from the checkpoint template
            if ($movement->checkpointTemplate) {
                $this->snapshotCheckpoints($job, $movement->checkpointTemplate);
            }

            // Update the movement with job info
            $movement->update([
                'job_id' => $job->job_id,
                'job_generated_at' => now(),
            ]);

            return $job;
        });
    }

    /**
     * Generate multiple jobs from movements.
     */
    public function generateJobsFromMovements(array $movementIds, ?array $options = []): array
    {
        $jobs = [];
        
        $movements = Movement::with('checkpointTemplate.checkpoints')
            ->whereIn('id', $movementIds)
            ->whereNull('job_id')
            ->get();

        foreach ($movements as $movement) {
            $jobs[] = $this->generateJobFromMovement($movement, $options);
        }

        return $jobs;
    }

    /**
     * Snapshot checkpoints from template to job.
     * 
     * This creates a historical snapshot so that changes to the template
     * don't affect existing jobs.
     */
    protected function snapshotCheckpoints(JobOperation $job, CheckpointTemplate $template): void
    {
        $checkpoints = $template->checkpoints()
            ->orderBy('pivot_order')
            ->get();

        $totalCheckpoints = $checkpoints->count();
        
        // Get movement start time for scheduling checkpoints
        $movement = $job->movement;
        $baseTime = $movement?->window_start ? 
            \Carbon\Carbon::parse($movement->window_start) : 
            now();
        
        $cumulativeMinutes = 0;

        foreach ($checkpoints as $checkpoint) {
            // Calculate scheduled time for this checkpoint
            $scheduledAt = $baseTime->copy()->addMinutes($cumulativeMinutes);
            
            JobCheckpoint::create([
                'job_id' => $job->id,
                'event_id' => $job->event_id,
                'checkpoint_id' => $checkpoint->id,
                'order' => $checkpoint->pivot->order,
                'name' => $checkpoint->name,
                'type' => $checkpoint->type,
                'description' => $checkpoint->description,
                'requires_photo' => $checkpoint->requires_photo,
                'requires_signature' => $checkpoint->requires_signature,
                'is_required' => $checkpoint->pivot->is_required ?? true,
                'estimated_minutes' => $checkpoint->pivot->estimated_minutes,
                'scheduled_at' => $scheduledAt,
                'state' => 'pending',
            ]);
            
            // Add to cumulative time for next checkpoint
            if ($checkpoint->pivot->estimated_minutes) {
                $cumulativeMinutes += $checkpoint->pivot->estimated_minutes;
            }
        }

        // Update job totals
        $job->update([
            'checkpoints_total' => $totalCheckpoints,
            'checkpoints_completed' => 0,
        ]);
    }

    /**
     * Generate a unique job ID.
     */
    protected function generateJobId(): string
    {
        $date = now()->format('Ymd');
        $count = JobOperation::whereDate('created_at', now())->count() + 1;
        
        return sprintf('JOB-%s-%04d', $date, $count);
    }

    /**
     * Create a plan from a movement template.
     */
    public function createPlanFromTemplate(
        MovementTemplate $template,
        array $planData,
        array $teamAssignments = []
    ): Plan {
        return DB::transaction(function () use ($template, $planData, $teamAssignments) {
            // Create the plan
            $plan = Plan::create([
                'code' => $this->generatePlanCode($planData['date']),
                'name' => $planData['name'],
                'date' => $planData['date'],
                'status' => $planData['status'] ?? 'draft',
                'event_id' => $planData['event_id'] ?? null,
                'movement_template_id' => $template->id,
                'notes' => $planData['notes'] ?? null,
                'created_by' => $planData['created_by'] ?? (Auth::check() ? Auth::id() : null),
            ]);

            // Get flight passenger count if flight_id is provided
            $flightPassengerCount = null;
            if (!empty($planData['flight_id'])) {
                $flight = \App\Models\TeamFlight::find($planData['flight_id']);
                $flightPassengerCount = $flight?->party_size_total;
            }

            // Create movements from template legs
            $legs = $template->legs()->with('checkpointTemplate')->get();
            
            // Track cumulative time for chaining movements
            $currentTime = $planData['base_time'] ?? now();
            
            // Check if we're creating movements for multiple teams (bulk mode)
            $bulkTeamIds = $teamAssignments['teams'] ?? null;
            $teamStartTimes = $teamAssignments['team_start_times'] ?? null;
            $teamFlightIds = $teamAssignments['team_flight_ids'] ?? null;
            
            // If team-specific start times are provided, loop through teams first
            if ($bulkTeamIds && $teamStartTimes) {
                // Bulk mode with per-team start times
                foreach ($bulkTeamIds as $teamId) {
                    $teamStartTime = isset($teamStartTimes[$teamId]) 
                        ? Carbon::parse($planData['date'] . ' ' . $teamStartTimes[$teamId], config('app.timezone'))
                        : $currentTime->copy();
                    
                    $teamCurrentTime = $teamStartTime->copy();
                    
                    // Get passenger count for this team from their flight
                    $teamPassengerCount = null;
                    $movementFlightId = null;
                    if (isset($teamFlightIds[$teamId]) && $teamFlightIds[$teamId]) {
                        $teamFlight = \App\Models\TeamFlight::find($teamFlightIds[$teamId]);
                        if ($teamFlight) {
                            $teamPassengerCount = $teamFlight->party_size_total;
                            $movementFlightId = $teamFlight->id;
                        }
                    }
                    
                    // Create all legs for this team
                    foreach ($legs as $leg) {
                        $scheduledDeparture = $teamCurrentTime->copy();
                        $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                        
                        // Update current time for next leg
                        $teamCurrentTime = $scheduledArrival->copy();
                        
                        // Determine passenger count: prioritize team's flight > team model > template estimate
                        $passengerCount = 0;
                        if ($teamPassengerCount !== null) {
                            $passengerCount = $teamPassengerCount;
                        } elseif ($teamId) {
                            $team = Team::find($teamId);
                            $passengerCount = $team?->party_size_total ?? 0;
                        }
                        if ($passengerCount === 0) {
                            $passengerCount = $leg->estimated_passengers ?? 0;
                        }
                        
                        // Auto-assign vehicle and driver
                        $vehicleId = $passengerCount > 0 
                            ? $this->findAvailableVehicle($passengerCount, $scheduledDeparture, $scheduledArrival)
                            : null;
                        
                        $driverId = $vehicleId 
                            ? $this->findAvailableDriver($scheduledDeparture, $scheduledArrival)
                            : null;
                        
                        // Determine if this movement should link to accommodation
                        $movementAccommodationId = null;
                        if (!empty($planData['accommodation_id'])) {
                            $movementAccommodationId = $planData['accommodation_id'];
                        }
                        
                        Movement::create([
                            'code' => $this->generateMovementCode($plan),
                            'plan_id' => $plan->id,
                            'event_id' => $plan->event_id,
                            'team_id' => $teamId,
                            'flight_id' => $movementFlightId,
                            'accommodation_id' => $movementAccommodationId,
                            'checkpoint_template_id' => $leg->checkpoint_template_id,
                            'kind' => $leg->leg_type,
                            'from_location' => $leg->from_location,
                            'to_location' => $leg->to_location,
                            'window_start' => $scheduledDeparture,
                            'window_end' => $scheduledArrival,
                            'vehicle_id' => $vehicleId,
                            'driver_id' => $driverId,
                            'passengers' => $passengerCount,
                            'status' => 'scheduled',
                            'source' => 'template',
                        ]);
                    }
                }
            } else {
                // Original logic: loop through legs first, then teams
                foreach ($legs as $leg) {
                    // Determine which teams to create movements for
                    $teamsForThisLeg = [];
                    
                    if ($bulkTeamIds) {
                        // Bulk mode: create movement for each team
                        $teamsForThisLeg = $bulkTeamIds;
                    } else {
                        // Single mode: use team assignment for this leg or default
                        $teamId = $teamAssignments[$leg->order] ?? $teamAssignments['default'] ?? null;
                        if ($teamId) {
                            $teamsForThisLeg = [$teamId];
                        }
                    }
                    
                    // Chain movements: each leg starts when the previous ends
                    $scheduledDeparture = $currentTime->copy();
                    $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                    
                    // Update current time for next leg
                    $currentTime = $scheduledArrival->copy();
                    
                    // Create a movement for each team
                    foreach ($teamsForThisLeg as $teamId) {
                        // Determine passenger count: prioritize flight > team > template estimate
                        $passengerCount = 0;
                        if ($flightPassengerCount !== null) {
                            // Use flight's passenger count if available
                            $passengerCount = $flightPassengerCount;
                        } elseif ($teamId) {
                            // Fall back to team's party size
                            $team = Team::find($teamId);
                            $passengerCount = $team?->party_size_total ?? 0;
                        }
                        if ($passengerCount === 0) {
                            // Finally, use template estimate
                            $passengerCount = $leg->estimated_passengers ?? 0;
                        }
                        
                        // Auto-assign vehicle based on passenger count and availability
                        $vehicleId = $passengerCount > 0 
                            ? $this->findAvailableVehicle($passengerCount, $scheduledDeparture, $scheduledArrival)
                            : null;
                        
                        // Auto-assign driver if vehicle was assigned
                        $driverId = $vehicleId 
                            ? $this->findAvailableDriver($scheduledDeparture, $scheduledArrival)
                            : null;
                        
                        // Determine if this movement should link to the flight
                        $movementFlightId = null;
                        if (!empty($planData['flight_id']) && in_array($leg->leg_type, ['arrival', 'departure'])) {
                            $movementFlightId = $planData['flight_id'];
                        }
                        
                        // Determine if this movement should link to accommodation
                        $movementAccommodationId = null;
                        if (!empty($planData['accommodation_id'])) {
                            // Link accommodation to relevant movement types (arrival, hotel transfer, etc.)
                            $movementAccommodationId = $planData['accommodation_id'];
                        }
                        
                        Movement::create([
                            'code' => $this->generateMovementCode($plan),
                            'plan_id' => $plan->id,
                            'event_id' => $plan->event_id,
                            'team_id' => $teamId,
                            'flight_id' => $movementFlightId,
                            'accommodation_id' => $movementAccommodationId,
                            'checkpoint_template_id' => $leg->checkpoint_template_id,
                            'kind' => $leg->leg_type,
                            'from_location' => $leg->from_location,
                            'to_location' => $leg->to_location,
                            'window_start' => $scheduledDeparture,
                            'window_end' => $scheduledArrival,
                            'vehicle_id' => $vehicleId,
                            'driver_id' => $driverId,
                            'passengers' => $passengerCount,
                            'status' => 'scheduled',
                            'source' => 'template',
                        ]);
                    }
                }
            }

            // Calculate movement count and unique team count
            $movementCount = Movement::where('plan_id', $plan->id)->count();
            $uniqueTeams = Movement::where('plan_id', $plan->id)->distinct('team_id')->count('team_id');

            $plan->update([
                'movements_count' => $movementCount,
                'teams_count' => $uniqueTeams,
            ]);

            return $plan;
        });
    }

    /**
     * Generate a unique plan code.
     */
    protected function generatePlanCode($date): string
    {
        // Format: PLN-2026-0430-001 (Year-MonthDay-Sequence)
        $dateObj = is_string($date) ? new \DateTime($date) : $date;
        $year = $dateObj->format('Y');
        $monthDay = $dateObj->format('md');
        $prefix = "PLN-{$year}-{$monthDay}";
        
        // Find the next available number for this date (including soft-deleted)
        $latestPlan = Plan::withTrashed()
            ->where('code', 'like', "{$prefix}%")
            ->orderBy('code', 'desc')
            ->first();

        if ($latestPlan) {
            // Extract the sequence number from the end (after last dash)
            $parts = explode('-', $latestPlan->code);
            $lastNumber = (int) end($parts);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        // Generate code and ensure it's unique (handle race conditions)
        $attempts = 0;
        do {
            $code = sprintf('%s-%03d', $prefix, $nextNumber);
            $exists = Plan::withTrashed()->where('code', $code)->exists();
            
            if ($exists) {
                $nextNumber++;
                $attempts++;
                if ($attempts > 100) {
                    throw new \Exception("Unable to generate unique plan code after 100 attempts");
                }
            }
        } while ($exists);

        return $code;
    }

    /**
     * Generate a unique movement code.
     */
    protected function generateMovementCode(Plan $plan): string
    {
        // Generate globally unique movement code
        $latestMovement = Movement::orderBy('id', 'desc')->first();
        $nextNumber = $latestMovement ? (intval(ltrim($latestMovement->code, 'M')) + 1) : 1;
        
        return sprintf('M%d', $nextNumber);
    }

    /**
     * Find an available vehicle with sufficient capacity.
     * Checks for time conflicts with existing movements.
     */
    protected function findAvailableVehicle(int $passengerCount, $scheduledDeparture, $scheduledArrival): ?int
    {
        // Find vehicles with sufficient capacity
        $vehicles = Vehicle::where('is_active', 1)
            ->where('capacity', '>=', $passengerCount)
            ->orderBy('capacity', 'asc') // Prefer smallest suitable vehicle
            ->get();

        foreach ($vehicles as $vehicle) {
            // Check if vehicle has conflicting movements at the same time
            $conflict = Movement::where('vehicle_id', $vehicle->id)
                ->where(function ($query) use ($scheduledDeparture, $scheduledArrival) {
                    $query->whereBetween('window_start', [$scheduledDeparture, $scheduledArrival])
                        ->orWhereBetween('window_end', [$scheduledDeparture, $scheduledArrival])
                        ->orWhere(function ($q) use ($scheduledDeparture, $scheduledArrival) {
                            $q->where('window_start', '<=', $scheduledDeparture)
                              ->where('window_end', '>=', $scheduledArrival);
                        });
                })
                ->exists();

            if (!$conflict) {
                return $vehicle->id;
            }
        }

        return null; // No available vehicle found
    }

    /**
     * Find an available driver.
     * Checks for time conflicts with existing movements.
     */
    protected function findAvailableDriver($scheduledDeparture, $scheduledArrival): ?int
    {
        $drivers = Driver::whereIn('status', ['available', 'on_shift'])->get();

        foreach ($drivers as $driver) {
            // Check if driver has conflicting movements at the same time
            $conflict = Movement::where('driver_id', $driver->id)
                ->where(function ($query) use ($scheduledDeparture, $scheduledArrival) {
                    $query->whereBetween('window_start', [$scheduledDeparture, $scheduledArrival])
                        ->orWhereBetween('window_end', [$scheduledDeparture, $scheduledArrival])
                        ->orWhere(function ($q) use ($scheduledDeparture, $scheduledArrival) {
                            $q->where('window_start', '<=', $scheduledDeparture)
                              ->where('window_end', '>=', $scheduledArrival);
                        });
                })
                ->exists();

            if (!$conflict) {
                return $driver->id;
            }
        }

        return null; // No available driver found
    }
}

