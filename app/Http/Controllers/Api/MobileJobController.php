<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobResource;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\JobCheckpoint;
use App\Models\JobIssue;
use App\Models\JobOperation;
use App\Services\CheckpointUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Job + checkpoint operations for the mobile supervisor app.
 */
class MobileJobController extends Controller
{
    public function __construct(private readonly CheckpointUploadService $uploads)
    {
    }

    /**
     * Jobs for the active event.
     */
    public function index(Request $request): JsonResponse
    {
        $jobs = JobOperation::with([
            'team',
            'movement.team',
            'vehicle',
            'driver',
            'supervisor',
            'checkpoints',
        ])
            ->where('jobs_operations.event_id', $this->activeEventId($request))
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
        $job->load([
            'team',
            'movement.team',
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

        $update = [
            'state' => 'done',
            'completed_by' => $request->user()->id,
            'completion_method' => 'mobile',
            'completed_at' => $this->resolveCompletedAt($checkpoint, $validated['actual_time'] ?? null),
            'notes' => $validated['notes'] ?? null,
        ];

        foreach (['planned_bags', 'bags_loaded', 'food_bags', 'oversized_pieces', 'gps_latitude', 'gps_longitude'] as $field) {
            if (array_key_exists($field, $validated)) {
                $update[$field] = $validated[$field];
            }
        }

        if ($request->hasFile('photo')) {
            $update['photo_path'] = $this->uploads->storePhoto(
                $request->file('photo'),
                $checkpoint->id,
                $checkpoint->job_id
            );
        }

        if (! empty($validated['signature'])) {
            $update['signature_path'] = $this->uploads->storeSignature(
                $validated['signature'],
                $checkpoint->id,
                $checkpoint->job_id
            );
        }

        // SLA is judged against the movement window, matching the web app.
        $movement = $job->movement;
        if ($movement?->window_end) {
            $completedAt = $update['completed_at'];
            $windowEnd = $movement->window_end;
            $isLate = $completedAt->greaterThan($windowEnd);
            $update['is_on_time'] = ! $isLate;
            $update['delay_minutes'] = $isLate ? $completedAt->diffInMinutes($windowEnd, true) : 0;
        }

        if ($checkpoint->scheduled_at) {
            $update['actual_duration_seconds'] = abs(
                $update['completed_at']->diffInSeconds($checkpoint->scheduled_at)
            );
        }

        $checkpoint->update($update);

        $job->updateProgress();
        $job->refresh();

        if ($job->status === 'pending') {
            $job->update(['status' => 'in-progress']);
        }

        if ($job->checkpoints_total > 0 && $job->checkpoints_completed === $job->checkpoints_total) {
            $job->update(['status' => 'completed', 'completed_at' => now()]);
        }

        $job->load([
            'team',
            'movement.team',
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

    public function photo(JobCheckpoint $checkpoint): StreamedResponse
    {
        abort_unless($checkpoint->photo_path, 404);
        abort_unless(Storage::disk('local')->exists($checkpoint->photo_path), 404);

        return Storage::disk('local')->response($checkpoint->photo_path);
    }

    public function signature(JobCheckpoint $checkpoint): StreamedResponse
    {
        abort_unless($checkpoint->signature_path, 404);
        abort_unless(Storage::disk('local')->exists($checkpoint->signature_path), 404);

        return Storage::disk('local')->response($checkpoint->signature_path);
    }

    /**
     * Mobile clients have no session, so resolve the flagged active event.
     */
    private function activeEventId(Request $request): ?int
    {
        return $request->integer('event_id')
            ?: Event::where('active_flag', true)->latest('id')->value('id')
            ?: Event::query()->latest('id')->value('id');
    }

    private function resolveCompletedAt(JobCheckpoint $checkpoint, ?string $actualTime): \Carbon\Carbon
    {
        if (! $actualTime) {
            return now();
        }

        [$hours, $minutes] = array_map('intval', explode(':', $actualTime));
        $completedAt = \Carbon\Carbon::today()->setTime($hours, $minutes);

        // A late-evening checkpoint confirmed just after midnight belongs to the
        // next day, not 24 hours in the past.
        if ($checkpoint->scheduled_at
            && $checkpoint->scheduled_at->hour >= 18
            && $hours < 6) {
            $completedAt->addDay();
        }

        return $completedAt;
    }
}
