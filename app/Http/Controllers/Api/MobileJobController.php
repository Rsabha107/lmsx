<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ScopesMobileAccess;
use App\Http\Resources\JobResource;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\JobCheckpoint;
use App\Models\JobIssue;
use App\Models\JobOperation;
use App\Services\CheckpointUploadService;
use App\Services\JobLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Job + checkpoint operations for the mobile supervisor app.
 */
class MobileJobController extends Controller
{
    use ScopesMobileAccess;

    public function __construct(
        private readonly CheckpointUploadService $uploads,
        private readonly JobLifecycleService $lifecycle,
    ) {
    }

    /**
     * Jobs for the active event, limited to the caller's functional areas.
     */
    public function index(Request $request): JsonResponse
    {
        $this->assertCanViewJobs($request);

        $query = JobOperation::with([
            'team',
            'movement.team',
            'movement.match.team1',
            'movement.match.team2',
            'movement.match.venue',
            'movement.flight.originAirport',
            'movement.flight.destinationAirport',
            'vehicle',
            'driver',
            'supervisor',
            'checkpoints',
        ])
            ->where('jobs_operations.event_id', $this->activeEventId($request));

        $jobs = $this->scopeToVisibleAreas($query, $request, 'jobs_operations.functional_area')
            ->leftJoin('movements', 'jobs_operations.movement_id', '=', 'movements.id')
            ->orderByRaw('movements.window_start IS NULL, movements.window_start asc')
            ->select('jobs_operations.*')
            ->get();

        return response()->json([
            'data' => JobResource::collection($jobs)->resolve(),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    public function show(Request $request, JobOperation $job): JsonResponse
    {
        $this->authorizeJobAccess($request, $job);

        $job->load([
            'team',
            'movement.team',
            'movement.match.team1',
            'movement.match.team2',
            'movement.match.venue',
            'movement.flight.originAirport',
            'movement.flight.destinationAirport',
            'vehicle',
            'driver',
            'supervisor',
            'checkpoints.completedBy',
            'checkpoints.checkpoint',
        ]);

        return response()->json(
            ['data' => JobResource::withCheckpoints($job)->resolve()]
        );
    }

    /**
     * Complete a checkpoint with optional photo / signature evidence.
     */
    public function completeCheckpoint(
        Request $request,
        JobOperation $job,
        JobCheckpoint $checkpoint
    ): JsonResponse {
        $this->authorizeJobAccess($request, $job);

        if ($checkpoint->job_id !== $job->id) {
            return response()->json(
                ['message' => 'Checkpoint does not belong to this job.'],
                422
            );
        }

        if ($checkpoint->state === 'done') {
            return response()->json(
                ['message' => 'Checkpoint already completed.'],
                409
            );
        }

        $validated = $request->validate([
            'actual_time' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:500',
            'photo' => 'nullable|image|max:10240',
            'signature' => 'nullable|string',
            'planned_bags' => 'nullable|integer|min:0',
            'bags_loaded' => 'nullable|integer|min:0',
            'food_bags' => 'nullable|integer|min:0',
            'oversized_pieces' => 'nullable|integer|min:0',
            'gps_latitude' => 'nullable|numeric|between:-90,90',
            'gps_longitude' => 'nullable|numeric|between:-180,180',
        ]);

        try {
            $this->lifecycle->completeCheckpoint(
                $checkpoint,
                $request->user(),
                [
                    'actual_time' => $validated['actual_time'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'photo' => $request->file('photo'),
                    'signature' => $validated['signature'] ?? null,
                    'planned_bags' => $validated['planned_bags'] ?? null,
                    'bags_loaded' => $validated['bags_loaded'] ?? null,
                    'food_bags' => $validated['food_bags'] ?? null,
                    'oversized_pieces' => $validated['oversized_pieces'] ?? null,
                    'gps_latitude' => $validated['gps_latitude'] ?? null,
                    'gps_longitude' => $validated['gps_longitude'] ?? null,
                ],
                'mobile',
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        $job->refresh();

        $job->load([
            'team',
            'movement.team',
            'movement.match.team1',
            'movement.match.team2',
            'movement.match.venue',
            'movement.flight.originAirport',
            'movement.flight.destinationAirport',
            'vehicle',
            'driver',
            'supervisor',
            'checkpoints.checkpoint',
        ]);

        return response()->json([
            'message' => 'Checkpoint completed.',
            'data' => JobResource::withCheckpoints($job)->resolve(),
        ]);
    }

    /**
     * Records a field-reported problem against a job.
     */
    public function reportIssue(Request $request, JobOperation $job): JsonResponse
    {
        $this->authorizeJobAccess($request, $job);

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(JobIssue::TYPES))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $issue = JobIssue::create([
            'job_id' => $job->id,
            'event_id' => $job->event_id,
            'reported_by' => $request->user()->id,
            'type' => $validated['type'],
            'severity' => JobIssue::TYPES[$validated['type']],
            'notes' => $validated['notes'] ?? null,
        ]);

        AuditLog::record(
            action: 'Issue reported',
            target: $job->job_id.' · '.$issue->label(),
            meta: $issue->notes,
            subject: $job,
            eventId: $job->event_id,
        );

        return response()->json([
            'message' => 'Issue reported.',
            'data' => [
                'id' => $issue->id,
                'type' => $issue->type,
                'label' => $issue->label(),
                'severity' => $issue->severity,
                'notes' => $issue->notes,
                'reported_at' => $issue->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function photo(Request $request, JobCheckpoint $checkpoint): StreamedResponse
    {
        $this->authorizeCheckpointAccess($request, $checkpoint);

        abort_unless($checkpoint->photo_path, 404);
        abort_unless(Storage::disk('local')->exists($checkpoint->photo_path), 404);

        return Storage::disk('local')->response($checkpoint->photo_path);
    }

    public function signature(Request $request, JobCheckpoint $checkpoint): StreamedResponse
    {
        $this->authorizeCheckpointAccess($request, $checkpoint);

        abort_unless($checkpoint->signature_path, 404);
        abort_unless(Storage::disk('local')->exists($checkpoint->signature_path), 404);

        return Storage::disk('local')->response($checkpoint->signature_path);
    }
}
