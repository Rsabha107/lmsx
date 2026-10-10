<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Concerns\ScopesMobileAccess;
use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use App\Services\JobLifecycleService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Controller for job execution and checkpoint completion.
 * 
 * This handles the field operations side of the logistics system:
 * - Viewing assigned jobs
 * - Completing checkpoints with evidence
 * - Tracking job progress
 *
 * Legacy token endpoints only. They use the same event, functional-area and own-job checks as the
 * maintained /api/mobile API; web job actions live in LmsController and routes/web/jobs.php.
 */
class JobOperationController extends Controller
{
    use ScopesMobileAccess;

    public function __construct(private readonly JobLifecycleService $lifecycle)
    {
    }

    /**
     * Mobile API: Get jobs for current supervisor.
     */
    public function myJobs(Request $request)
    {
        $this->assertCanViewJobs($request);

        $query = JobOperation::supervisedBy($request->user()->id)
            ->where('event_id', $this->activeEventId($request))
            ->whereIn('status', ['dispatched', 'in-progress']);

        $jobs = $this->scopeToVisibleAreas($query, $request)
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
        $this->authorizeCheckpointAccess($request, $checkpoint);

        $validated = $request->validate([
            'photo' => 'nullable|image|max:5120',
            'gps_latitude' => 'nullable|numeric|between:-90,90',
            'gps_longitude' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $checkpoint = $this->lifecycle->completeCheckpoint(
                $checkpoint,
                $request->user(),
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
    public function progress(Request $request, JobOperation $job)
    {
        $this->authorizeJobAccess($request, $job);

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
