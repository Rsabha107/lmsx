<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use App\Services\JobLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use RuntimeException;

/**
 * Controller for job execution and checkpoint completion.
 * 
 * This handles the field operations side of the logistics system:
 * - Viewing assigned jobs
 * - Dispatching and starting jobs
 * - Completing checkpoints with evidence
 * - Tracking job progress
 */
class JobOperationController extends Controller
{
    public function __construct(private readonly JobLifecycleService $lifecycle)
    {
    }

    /**
     * List all jobs (with filtering).
     */
    public function index(Request $request)
    {
        $query = JobOperation::with([
            'event',
            'movement.team',
            'movement.plan',
            'vehicle',
            'driver',
            'supervisor',
        ]);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by supervisor (for field staff viewing their assignments)
        if ($request->has('supervisor_id')) {
            $query->where('supervisor_id', $request->supervisor_id);
        }

        // Filter by date
        if ($request->has('date')) {
            $query->whereHas('movement', function ($q) use ($request) {
                $q->whereDate('window_start', $request->date);
            });
        }

        // Active jobs only
        if ($request->boolean('active_only')) {
            $query->active();
        }

        $jobs = $query->orderBy('created_at', 'desc')->paginate(20);

        return Inertia::render('Jobs', [
            'jobs' => $jobs,
            'filters' => $request->only(['status', 'supervisor_id', 'date', 'active_only']),
        ]);
    }

    /**
     * Show a single job with its checkpoints.
     */
    public function show(JobOperation $job)
    {
        $job->load([
            'event',
            'movement.team',
            'movement.plan',
            'movement.checkpointTemplate',
            'vehicle',
            'driver',
            'supervisor',
            'checkpoints.completedBy',
        ]);

        // Calculate statistics
        $stats = [
            'total_checkpoints' => $job->checkpoints_total,
            'completed_checkpoints' => $job->checkpoints_completed,
            'progress_percentage' => $job->progress_percentage,
            'pending_count' => $job->checkpoints()->pending()->count(),
            'next_checkpoint' => $job->checkpoints()->pending()->orderBy('order')->first(),
        ];

        return Inertia::render('Jobs/Show', [
            'job' => $job,
            'stats' => $stats,
        ]);
    }

    /**
     * Dispatch a job (mark as ready for field execution).
     */
    public function dispatch(Request $request, JobOperation $job)
    {
        try {
            $this->lifecycle->transitionStatus($job, 'dispatched', Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Job {$job->job_id} dispatched successfully");
    }

    /**
     * Start a job (field execution begins).
     */
    public function start(Request $request, JobOperation $job)
    {
        try {
            $this->lifecycle->transitionStatus($job, 'in-progress', Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // A leading dispatch checkpoint is satisfied by the act of starting.
        $firstCheckpoint = $job->checkpoints()->orderBy('order')->first();
        if ($firstCheckpoint && $firstCheckpoint->type === 'dispatch' && $firstCheckpoint->state !== 'done') {
            $this->lifecycle->completeCheckpoint($firstCheckpoint, Auth::user(), [], 'auto');
        }

        return back()->with('success', "Job {$job->job_id} started");
    }

    /**
     * Complete a job.
     */
    public function complete(Request $request, JobOperation $job)
    {
        // Check if all required checkpoints are completed
        $pendingRequired = $job->checkpoints()
            ->where('state', 'pending')
            ->whereIn('type', ['arrival', 'boarding', 'departure', 'handoff'])
            ->count();

        if ($pendingRequired > 0) {
            return back()->with('error', "Cannot complete job. {$pendingRequired} required checkpoints are still pending");
        }

        try {
            $this->lifecycle->transitionStatus($job, 'completed', Auth::user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('jobs.index')->with('success', "Job {$job->job_id} completed successfully");
    }

    /**
     * Complete a checkpoint with evidence.
     */
    public function completeCheckpoint(Request $request, JobOperation $job, JobCheckpoint $checkpoint)
    {
        // Verify checkpoint belongs to this job
        if ($checkpoint->job_id !== $job->id) {
            return back()->with('error', 'Checkpoint does not belong to this job');
        }

        $validated = $request->validate([
            'completion_method' => 'required|in:mobile,web,auto,gps',
            'photo' => 'nullable|image|max:5120', // 5MB max
            'signature' => 'nullable|string', // Base64 signature data
            'gps_latitude' => 'nullable|numeric|between:-90,90',
            'gps_longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->lifecycle->completeCheckpoint(
                $checkpoint,
                Auth::user(),
                [
                    'notes' => $validated['notes'] ?? null,
                    'photo' => $request->file('photo'),
                    'signature' => $validated['signature'] ?? null,
                    'gps_latitude' => $validated['gps_latitude'] ?? null,
                    'gps_longitude' => $validated['gps_longitude'] ?? null,
                ],
                $validated['completion_method'],
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $job->fresh()->status === 'completed'
            ? 'Checkpoint completed! All checkpoints done - job completed.'
            : 'Checkpoint completed successfully');
    }

    /**
     * Skip a checkpoint (with reason).
     */
    public function skipCheckpoint(Request $request, JobOperation $job, JobCheckpoint $checkpoint)
    {
        // Verify checkpoint belongs to this job
        if ($checkpoint->job_id !== $job->id) {
            return back()->with('error', 'Checkpoint does not belong to this job');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $this->lifecycle->skipCheckpoint($checkpoint, Auth::user(), $validated['reason']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('warning', 'Checkpoint skipped');
    }

    /**
     * Mobile API: Get jobs for current supervisor.
     */
    public function myJobs(Request $request)
    {
        $user = Auth::user();

        $jobs = JobOperation::where('supervisor_id', $user->id)
            ->whereIn('status', ['dispatched', 'in-progress'])
            ->with([
                'movement.team',
                'vehicle',
                'driver',
                'checkpoints' => fn($q) => $q->orderBy('order'),
            ])
            ->orderBy('dispatched_at')
            ->get();

        return response()->json([
            'jobs' => $jobs,
        ]);
    }

    /**
     * Mobile API: Quick checkpoint completion (optimized for mobile).
     */
    public function quickCompleteCheckpoint(Request $request, JobCheckpoint $checkpoint)
    {
        $validated = $request->validate([
            'photo' => 'nullable|image|max:5120',
            'gps_latitude' => 'nullable|numeric|between:-90,90',
            'gps_longitude' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $checkpoint = $this->lifecycle->completeCheckpoint(
                $checkpoint,
                Auth::user(),
                [
                    'photo' => $request->file('photo'),
                    'gps_latitude' => $validated['gps_latitude'] ?? null,
                    'gps_longitude' => $validated['gps_longitude'] ?? null,
                ],
                'mobile',
            );
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'checkpoint' => $checkpoint,
            'job_progress' => $checkpoint->job->fresh()->progress_percentage,
        ]);
    }

    /**
     * Get job progress/status (for real-time updates).
     */
    public function progress(JobOperation $job)
    {
        return response()->json([
            'job_id' => $job->job_id,
            'status' => $job->status,
            'progress_percentage' => $job->progress_percentage,
            'checkpoints_completed' => $job->checkpoints_completed,
            'checkpoints_total' => $job->checkpoints_total,
            'checkpoints' => $job->checkpoints->map(fn($cp) => [
                'id' => $cp->id,
                'name' => $cp->name,
                'order' => $cp->order,
                'state' => $cp->state,
                'completed_at' => $cp->completed_at,
            ]),
        ]);
    }
}
