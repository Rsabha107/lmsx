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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
     *
     * With ?updated_since=<synced_at from the last call> only jobs changed since then come back
     * (the job, its checkpoints or its movement). visible_ids is always the full visible set, so the
     * app can drop jobs that were reassigned or removed. Keep synced_at, not the device clock.
     */
    public function index(Request $request): JsonResponse
    {
        $this->assertCanViewJobs($request);

        $since = $request->validate(['updated_since' => 'nullable|date'])['updated_since'] ?? null;
        $since = $since ? Carbon::parse($since) : null;

        // Taken before the query so a change landing mid-request is picked up next time.
        $syncedAt = now();

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

        $this->scopeToVisibleAreas($query, $request, 'jobs_operations.functional_area');

        $visibleIds = (clone $query)->pluck('jobs_operations.id')->values();

        if ($since) {
            $query->where(fn ($q) => $q
                ->where('jobs_operations.updated_at', '>=', $since)
                ->orWhereHas('checkpoints', fn ($c) => $c->where('job_checkpoints.updated_at', '>=', $since))
                ->orWhereHas('movement', fn ($m) => $m->where('movements.updated_at', '>=', $since)));
        }

        $jobs = $query
            ->leftJoin('movements', 'jobs_operations.movement_id', '=', 'movements.id')
            ->orderByRaw('movements.window_start IS NULL, movements.window_start asc')
            ->select('jobs_operations.*')
            ->get();

        return response()->json([
            'data' => JobResource::collection($jobs)->resolve(),
            'visible_ids' => $visibleIds,
            'delta' => $since !== null,
            'synced_at' => $syncedAt->toIso8601String(),
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
     *
     * Replay-safe: send the same Idempotency-Key (or client_op_id) on every retry of one queued
     * action. A retry after the first attempt committed returns 200 with the current job instead
     * of a 409, flagged by the Idempotent-Replayed header.
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

        $opId = $this->operationId($request);

        if ($this->isReplay($checkpoint, $request, $opId)) {
            return $this->checkpointResponse($job, 'Checkpoint already completed.', replayed: true);
        }

        if ($checkpoint->state === 'done') {
            return response()->json(
                ['message' => 'Checkpoint already completed.'],
                409
            );
        }

        $validated = $request->validate([
            'actual_time' => 'nullable|date_format:H:i',
            // ISO 8601 with an offset (2026-10-09T14:05:00+03:00): when the supervisor acted, and the
            // device clock when it was sent. The offset is required so skew can be measured.
            'event_at' => ['nullable', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/'],
            'client_sent_at' => ['nullable', 'required_with:event_at', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/'],
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
                    'event_at' => $validated['event_at'] ?? null,
                    'client_sent_at' => $validated['client_sent_at'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'photo' => $request->file('photo'),
                    'signature' => $validated['signature'] ?? null,
                    'planned_bags' => $validated['planned_bags'] ?? null,
                    'bags_loaded' => $validated['bags_loaded'] ?? null,
                    'food_bags' => $validated['food_bags'] ?? null,
                    'oversized_pieces' => $validated['oversized_pieces'] ?? null,
                    'gps_latitude' => $validated['gps_latitude'] ?? null,
                    'gps_longitude' => $validated['gps_longitude'] ?? null,
                    'client_op_id' => $opId,
                ],
                'mobile',
            );
        } catch (RuntimeException $e) {
            // A concurrent duplicate can lose the race to the row lock; it is still a replay.
            if ($this->isReplay($checkpoint->refresh(), $request, $opId)) {
                return $this->checkpointResponse($job, 'Checkpoint already completed.', replayed: true);
            }

            return response()->json(['message' => $e->getMessage()], 409);
        }

        return $this->checkpointResponse($job, 'Checkpoint completed.');
    }

    private function checkpointResponse(JobOperation $job, string $message, bool $replayed = false): JsonResponse
    {
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
            'message' => $message,
            'data' => JobResource::withCheckpoints($job)->resolve(),
        ], 200, $replayed ? ['Idempotent-Replayed' => 'true'] : []);
    }

    /** The client's key for one queued action, or null when it sent none. */
    private function operationId(Request $request): ?string
    {
        $key = $request->header('Idempotency-Key') ?? $request->input('client_op_id');

        if ($key === null || $key === '') {
            return null;
        }

        Validator::make(['key' => $key], ['key' => ['string', 'regex:/^[A-Za-z0-9._:\-]{8,64}$/']], [
            'key.regex' => 'The idempotency key must be 8-64 letters, digits or . _ : -',
        ])->validate();

        return $key;
    }

    /** Same user, same key, already done: the earlier attempt succeeded and only its reply was lost. */
    private function isReplay(JobCheckpoint $checkpoint, Request $request, ?string $opId): bool
    {
        return $opId !== null
            && $checkpoint->state === 'done'
            && $checkpoint->client_op_id === $opId
            && (int) $checkpoint->completed_by === (int) $request->user()->id;
    }

    /**
     * Records a field-reported problem against a job. Replay-safe with the same Idempotency-Key.
     */
    public function reportIssue(Request $request, JobOperation $job): JsonResponse
    {
        $this->authorizeJobAccess($request, $job);

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(JobIssue::TYPES))],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $opId = $this->operationId($request);
        $existing = $opId ? $this->issueFor($job, $request, $opId) : null;

        if ($existing) {
            return $this->issueResponse($existing, 200, replayed: true);
        }

        try {
            $issue = JobIssue::create([
                'job_id' => $job->id,
                'event_id' => $job->event_id,
                'reported_by' => $request->user()->id,
                'type' => $validated['type'],
                'severity' => JobIssue::TYPES[$validated['type']],
                'notes' => $validated['notes'] ?? null,
                'client_op_id' => $opId,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent duplicate won the unique index.
            return $this->issueResponse($this->issueFor($job, $request, $opId) ?? throw $e, 200, replayed: true);
        }

        AuditLog::record(
            action: 'Issue reported',
            target: $job->job_id.' · '.$issue->label(),
            meta: $issue->notes,
            subject: $job,
            eventId: $job->event_id,
        );

        return $this->issueResponse($issue, 201);
    }

    private function issueFor(JobOperation $job, Request $request, string $opId): ?JobIssue
    {
        return JobIssue::where('job_id', $job->id)
            ->where('reported_by', $request->user()->id)
            ->where('client_op_id', $opId)
            ->first();
    }

    private function issueResponse(JobIssue $issue, int $status, bool $replayed = false): JsonResponse
    {
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
        ], $status, $replayed ? ['Idempotent-Replayed' => 'true'] : []);
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
