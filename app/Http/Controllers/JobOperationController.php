<?php

namespace App\Http\Controllers;

use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

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
    /**
     * List all jobs (with filtering).
     */
    public function index(Request $request)
    {
        $query = JobOperation::with([
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
        if ($job->status !== 'pending') {
            return back()->with('error', 'Job is not in pending status');
        }

        $job->update([
            'status' => 'dispatched',
            'dispatched_at' => now(),
        ]);

        // Optionally send notification to supervisor/driver
        // event(new JobDispatched($job));

        return back()->with('success', "Job {$job->job_id} dispatched successfully");
    }

    /**
     * Start a job (field execution begins).
     */
    public function start(Request $request, JobOperation $job)
    {
        if (!in_array($job->status, ['pending', 'dispatched'])) {
            return back()->with('error', 'Job cannot be started in current status');
        }

        $job->update([
            'status' => 'in-progress',
            'started_at' => now(),
        ]);

        // Auto-complete first checkpoint if it's auto-dispatch
        $firstCheckpoint = $job->checkpoints()->orderBy('order')->first();
        if ($firstCheckpoint && $firstCheckpoint->type === 'dispatch') {
            $firstCheckpoint->update([
                'state' => 'done',
                'completed_at' => now(),
                'completed_by' => Auth::id(),
                'completion_method' => 'auto',
            ]);
            $job->updateProgress();
        }

        return back()->with('success', "Job {$job->job_id} started");
    }

    /**
     * Complete a job.
     */
    public function complete(Request $request, JobOperation $job)
    {
        if ($job->status !== 'in-progress') {
            return back()->with('error', 'Job is not in progress');
        }

        // Check if all required checkpoints are completed
        $pendingRequired = $job->checkpoints()
            ->where('state', 'pending')
            ->whereIn('type', ['arrival', 'boarding', 'departure', 'handoff'])
            ->count();

        if ($pendingRequired > 0) {
            return back()->with('error', "Cannot complete job. {$pendingRequired} required checkpoints are still pending");
        }

        $job->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

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

        if ($checkpoint->state === 'done') {
            return back()->with('error', 'Checkpoint already completed');
        }

        $validated = $request->validate([
            'completion_method' => 'required|in:mobile,web,auto,gps',
            'photo' => 'nullable|image|max:5120', // 5MB max
            'signature' => 'nullable|string', // Base64 signature data
            'gps_latitude' => 'nullable|numeric',
            'gps_longitude' => 'nullable|numeric',
            'notes' => 'nullable|string|max:500',
        ]);

        $evidence = [];

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('checkpoints/photos', 'public');
            $evidence['photo'] = $path;
        }

        // Handle signature
        if ($request->has('signature')) {
            $signatureData = $request->signature;
            // Save base64 signature as image
            $signaturePath = 'checkpoints/signatures/' . uniqid() . '.png';
            Storage::disk('public')->put($signaturePath, base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $signatureData)));
            $evidence['signature'] = $signaturePath;
        }

        // Handle GPS
        if ($request->has('gps_latitude') && $request->has('gps_longitude')) {
            $evidence['gps_latitude'] = $request->gps_latitude;
            $evidence['gps_longitude'] = $request->gps_longitude;
        }

        // Handle notes
        if ($request->has('notes')) {
            $evidence['notes'] = $request->notes;
        }

        // Mark checkpoint as done
        $checkpoint->markAsDone(Auth::user(), $validated['completion_method'], $evidence);

        // Auto-start job if it's still pending
        if ($job->fresh()->status === 'pending') {
            $job->update(['status' => 'in-progress']);
        }

        // Check if this was the last checkpoint
        if ($job->fresh()->checkpoints_completed === $job->checkpoints_total) {
            // Auto-complete job if all checkpoints are done
            $job->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            return back()->with('success', 'Checkpoint completed! All checkpoints done - job completed.');
        }

        return back()->with('success', 'Checkpoint completed successfully');
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

        if ($checkpoint->state !== 'pending') {
            return back()->with('error', 'Only pending checkpoints can be skipped');
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $checkpoint->update([
            'state' => 'skipped',
            'completed_at' => now(),
            'completed_by' => Auth::id(),
            'notes' => 'SKIPPED: ' . $validated['reason'],
        ]);

        // Auto-start job if it's still pending
        if ($job->fresh()->status === 'pending') {
            $job->update(['status' => 'in-progress']);
        }

        // Note: We don't update job progress for skipped checkpoints
        // They don't count towards completion

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
            'gps_latitude' => 'nullable|numeric',
            'gps_longitude' => 'nullable|numeric',
        ]);

        $evidence = [];

        if ($request->hasFile('photo')) {
            $evidence['photo'] = $request->file('photo')->store('checkpoints/photos', 'public');
        }

        if ($request->has('gps_latitude') && $request->has('gps_longitude')) {
            $evidence['gps_latitude'] = $request->gps_latitude;
            $evidence['gps_longitude'] = $request->gps_longitude;
        }

        $checkpoint->markAsDone(Auth::user(), 'mobile', $evidence);

        // Auto-start job if it's still pending
        $job = $checkpoint->job->fresh();
        if ($job->status === 'pending') {
            $job->update(['status' => 'in-progress']);
        }

        return response()->json([
            'success' => true,
            'checkpoint' => $checkpoint->fresh(),
            'job_progress' => $job->progress_percentage,
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
