<?php

namespace App\Http\Controllers;

use App\Models\JobOperation;
use App\Models\JobCheckpoint;
use App\Models\Movement;
use App\Models\Plan;
use App\Models\Vehicle;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class AnalyticsController extends Controller
{
    /**
     * Display the analytics dashboard
     */
    public function index(Request $request)
    {
        $period = $request->get('period', '7d'); // Default to last 7 days
        
        return Inertia::render('Analytics', [
            'realTimeStats' => $this->getRealTimeStats(),
            'performanceMetrics' => $this->getPerformanceMetrics($period),
            'resourceUtilization' => $this->getResourceUtilization($period),
            'complianceMetrics' => $this->getComplianceMetrics($period),
            'checkpointAnalysis' => $this->getCheckpointAnalysis($period),
            'teamPatterns' => $this->getTeamPatterns($period),
            'planEffectiveness' => $this->getPlanEffectiveness($period),
            'trendsData' => $this->getTrendsData($period),
        ]);
    }

    /**
     * Get real-time operational statistics
     */
    protected function getRealTimeStats()
    {
        return [
            'active_jobs' => JobOperation::where('status', 'in-progress')->count(),
            'delayed_jobs' => JobOperation::where('status', 'delayed')->count(),
            'completed_today' => JobOperation::where('status', 'completed')
                ->whereDate('updated_at', today())
                ->count(),
            'pending_jobs' => JobOperation::where('status', 'pending')->count(),
            'vehicles_active' => Vehicle::where('status', 'on_job')->count(),
            'drivers_on_shift' => Driver::where('status', 'on_shift')->count(),
            'checkpoints_completed_today' => JobCheckpoint::where('state', 'done')
                ->whereDate('completed_at', today())
                ->count(),
        ];
    }

    /**
     * Get performance and efficiency metrics
     */
    protected function getPerformanceMetrics($period)
    {
        $startDate = $this->getPeriodStartDate($period);
        
        // Get completed jobs in period
        $completedJobs = JobOperation::with(['movement', 'checkpoints'])
            ->where('status', 'completed')
            ->where('updated_at', '>=', $startDate)
            ->get();

        $totalJobs = $completedJobs->count();
        
        if ($totalJobs === 0) {
            return [
                'on_time_percentage' => 0,
                'average_delay_minutes' => 0,
                'checkpoint_adherence' => 0,
                'jobs_analyzed' => 0,
            ];
        }

        // Calculate on-time performance
        $onTimeJobs = $completedJobs->filter(function ($job) {
            return !$job->movement || $job->movement->delay_minutes === null || $job->movement->delay_minutes <= 0;
        })->count();

        // Calculate average delay
        $totalDelay = $completedJobs->sum(function ($job) {
            return $job->movement?->delay_minutes ?? 0;
        });

        // Calculate checkpoint adherence
        $allCheckpoints = JobCheckpoint::whereIn('job_id', $completedJobs->pluck('id'))
            ->where('state', 'done')
            ->get();
        
        $onTimeCheckpoints = $allCheckpoints->filter(function ($checkpoint) {
            if (!$checkpoint->scheduled_at || !$checkpoint->completed_at) {
                return true;
            }
            $scheduled = Carbon::parse($checkpoint->scheduled_at);
            $completed = Carbon::parse($checkpoint->completed_at);
            return $completed->lte($scheduled->addMinutes(5)); // 5 min grace period
        })->count();

        return [
            'on_time_percentage' => round(($onTimeJobs / $totalJobs) * 100, 1),
            'average_delay_minutes' => round($totalDelay / $totalJobs, 1),
            'checkpoint_adherence' => $allCheckpoints->count() > 0 
                ? round(($onTimeCheckpoints / $allCheckpoints->count()) * 100, 1) 
                : 0,
            'jobs_analyzed' => $totalJobs,
        ];
    }

    /**
     * Get resource utilization metrics
     */
    protected function getResourceUtilization($period)
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $totalVehicles = Vehicle::count();
        $totalDrivers = Driver::count();
        
        $jobsInPeriod = JobOperation::where('created_at', '>=', $startDate)->count();
        
        // Vehicle utilization
        $vehicleUsage = JobOperation::where('created_at', '>=', $startDate)
            ->whereNotNull('vehicle_id')
            ->distinct('vehicle_id')
            ->count();
        
        // Driver utilization
        $driverUsage = JobOperation::where('created_at', '>=', $startDate)
            ->whereNotNull('driver_id')
            ->distinct('driver_id')
            ->count();

        return [
            'vehicle_utilization_rate' => $totalVehicles > 0 ? round(($vehicleUsage / $totalVehicles) * 100, 1) : 0,
            'driver_utilization_rate' => $totalDrivers > 0 ? round(($driverUsage / $totalDrivers) * 100, 1) : 0,
            'total_vehicles' => $totalVehicles,
            'total_drivers' => $totalDrivers,
            'jobs_in_period' => $jobsInPeriod,
        ];
    }

    /**
     * Get compliance metrics
     */
    protected function getComplianceMetrics($period)
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $checkpoints = JobCheckpoint::where('state', 'done')
            ->where('completed_at', '>=', $startDate)
            ->get();

        $totalCheckpoints = $checkpoints->count();
        
        if ($totalCheckpoints === 0) {
            return [
                'photo_compliance' => 0,
                'signature_compliance' => 0,
                'override_rate' => 0,
                'checkpoints_analyzed' => 0,
            ];
        }

        $photoRequired = $checkpoints->where('requires_photo', true)->count();
        $photoCaptured = $checkpoints->where('requires_photo', true)
            ->filter(fn($c) => !empty($c->photo_path) || !empty($c->photo_data))
            ->count();

        $signatureRequired = $checkpoints->where('requires_signature', true)->count();
        $signatureCaptured = $checkpoints->where('requires_signature', true)
            ->filter(fn($c) => !empty($c->signature_path) || !empty($c->signature_data))
            ->count();

        $overrides = JobCheckpoint::where('completed_at', '>=', $startDate)
            ->where('was_overridden', true)
            ->count();

        return [
            'photo_compliance' => $photoRequired > 0 ? round(($photoCaptured / $photoRequired) * 100, 1) : 100,
            'signature_compliance' => $signatureRequired > 0 ? round(($signatureCaptured / $signatureRequired) * 100, 1) : 100,
            'override_rate' => round(($overrides / $totalCheckpoints) * 100, 1),
            'checkpoints_analyzed' => $totalCheckpoints,
        ];
    }

    /**
     * Get checkpoint analysis
     */
    protected function getCheckpointAnalysis($period)
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $checkpoints = JobCheckpoint::where('state', 'done')
            ->where('completed_at', '>=', $startDate)
            ->get();

        // Group by type and calculate averages
        $byType = $checkpoints->groupBy('type')->map(function ($group) {
            $durations = $group->filter(function ($checkpoint) {
                return $checkpoint->scheduled_at && $checkpoint->completed_at;
            })->map(function ($checkpoint) {
                $scheduled = Carbon::parse($checkpoint->scheduled_at);
                $completed = Carbon::parse($checkpoint->completed_at);
                return $completed->diffInMinutes($scheduled, false);
            });

            return [
                'count' => $group->count(),
                'avg_variance' => $durations->count() > 0 ? round($durations->average(), 1) : 0,
            ];
        });

        return $byType->toArray();
    }

    /**
     * Get team and movement patterns
     */
    protected function getTeamPatterns($period)
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $movements = Movement::with('team')
            ->where('created_at', '>=', $startDate)
            ->get();

        // Movement kind distribution
        $kindDistribution = $movements->groupBy('kind')->map->count();

        // Average passengers per type
        $avgPassengers = $movements->groupBy('kind')->map(function ($group) {
            return round($group->average('passengers'), 1);
        });

        return [
            'kind_distribution' => $kindDistribution->toArray(),
            'avg_passengers_by_kind' => $avgPassengers->toArray(),
            'total_movements' => $movements->count(),
        ];
    }

    /**
     * Get plan effectiveness metrics
     */
    protected function getPlanEffectiveness($period)
    {
        $startDate = $this->getPeriodStartDate($period);
        
        $plans = Plan::where('created_at', '>=', $startDate)->get();
        
        $completedPlans = $plans->where('status', 'completed')->count();
        $avgMovementsPerPlan = $plans->count() > 0 ? round($plans->sum(function ($plan) {
            return $plan->movements()->count();
        }) / $plans->count(), 1) : 0;

        return [
            'total_plans' => $plans->count(),
            'completed_plans' => $completedPlans,
            'active_plans' => $plans->where('status', 'active')->count(),
            'avg_movements_per_plan' => $avgMovementsPerPlan,
        ];
    }

    /**
     * Get trend data for charts
     */
    protected function getTrendsData($period)
    {
        $startDate = $this->getPeriodStartDate($period);
        $days = Carbon::now()->diffInDays($startDate);
        
        // Daily job completion trend
        $dailyJobs = JobOperation::where('status', 'completed')
            ->where('updated_at', '>=', $startDate)
            ->selectRaw('DATE(updated_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        // Daily delay trend
        $dailyDelays = Movement::where('updated_at', '>=', $startDate)
            ->whereNotNull('delay_minutes')
            ->selectRaw('DATE(updated_at) as date, AVG(delay_minutes) as avg_delay')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return ['date' => $item->date, 'avg_delay' => round($item->avg_delay, 1)];
            });

        return [
            'daily_jobs' => $dailyJobs->toArray(),
            'daily_delays' => $dailyDelays->toArray(),
        ];
    }

    /**
     * Export analytics data
     */
    public function export(Request $request)
    {
        $period = $request->get('period', '7d');
        $format = $request->get('format', 'json'); // json, csv
        
        $data = [
            'exported_at' => now()->toIso8601String(),
            'period' => $period,
            'real_time_stats' => $this->getRealTimeStats(),
            'performance_metrics' => $this->getPerformanceMetrics($period),
            'resource_utilization' => $this->getResourceUtilization($period),
            'compliance_metrics' => $this->getComplianceMetrics($period),
            'checkpoint_analysis' => $this->getCheckpointAnalysis($period),
            'team_patterns' => $this->getTeamPatterns($period),
            'plan_effectiveness' => $this->getPlanEffectiveness($period),
        ];

        if ($format === 'csv') {
            // Flatten data for CSV
            $csv = $this->arrayToCsv($data);
            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="analytics-export-' . now()->format('Y-m-d') . '.csv"',
            ]);
        }

        // Default JSON export
        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="analytics-export-' . now()->format('Y-m-d') . '.json"',
        ]);
    }

    /**
     * Convert array to CSV
     */
    protected function arrayToCsv($data, $prefix = '')
    {
        $csv = '';
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                $csv .= $this->arrayToCsv($value, $fullKey);
            } else {
                $csv .= "{$fullKey},{$value}\n";
            }
        }
        return $csv;
    }

    /**
     * Get start date based on period
     */
    protected function getPeriodStartDate($period)
    {
        return match($period) {
            '24h' => Carbon::now()->subDay(),
            '7d' => Carbon::now()->subDays(7),
            '30d' => Carbon::now()->subDays(30),
            '90d' => Carbon::now()->subDays(90),
            default => Carbon::now()->subDays(7),
        };
    }
}
