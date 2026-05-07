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
                'movement_template_id' => $template->id,
                'notes' => $planData['notes'] ?? null,
                'created_by' => $planData['created_by'] ?? (Auth::check() ? Auth::id() : null),
            ]);

            // Create movements from template legs
            $legs = $template->legs()->with('checkpointTemplate')->get();
            
            // Track cumulative time for chaining movements
            $currentTime = $planData['base_time'] ?? now();
            
            foreach ($legs as $leg) {
                $teamId = $teamAssignments[$leg->order] ?? $teamAssignments['default'] ?? null;
                
                // Chain movements: each starts when the previous ends
                $scheduledDeparture = $currentTime->copy();
                $scheduledArrival = $scheduledDeparture->copy()->addMinutes($leg->estimated_duration_minutes ?? 30);
                
                // Update current time for next movement
                $currentTime = $scheduledArrival->copy();
                
                // Determine passenger count: prioritize team's party size, then template estimate
                $passengerCount = 0;
                if ($teamId) {
                    $team = Team::find($teamId);
                    $passengerCount = $team?->party_size_total ?? 0;
                }
                if ($passengerCount === 0) {
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
                
                Movement::create([
                    'code' => $this->generateMovementCode($plan),
                    'plan_id' => $plan->id,
                    'team_id' => $teamId,
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

            $plan->update([
                'movements_count' => $legs->count(),
                'teams_count' => count(array_unique(array_values($teamAssignments))),
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

