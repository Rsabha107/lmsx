<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobCheckpoint extends Model
{
    protected $fillable = [
        'job_id',
        'checkpoint_id',
        'order',
        'name',
        'type',
        'description',
        'requires_photo',
        'requires_signature',
        'is_required',
        'estimated_minutes',
        'state',
        'scheduled_at',
        'started_at',
        'completed_at',
        'completed_by',
        'completion_method',
        'actual_duration_seconds',
        'is_on_time',
        'delay_minutes',
        'skip_reason',
        'skipped_by',
        'skipped_at',
        'exception_type',
        'photo_path',
        'photo_data',
        'signature_path',
        'signature_data',
        'gps_latitude',
        'gps_longitude',
        'location_name',
        'gps_accuracy_meters',
        'verification_code',
        'verified_by',
        'verified_at',
        'notes',
        'was_overridden',
        'override_reason',
        'override_notes',
        'overridden_by',
        'overridden_at',
        'override_actual_time',
        'bags_loaded',
        'oversized_pieces',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'skipped_at' => 'datetime',
        'verified_at' => 'datetime',
        'overridden_at' => 'datetime',
        'requires_photo' => 'boolean',
        'requires_signature' => 'boolean',
        'is_required' => 'boolean',
        'is_on_time' => 'boolean',
        'was_overridden' => 'boolean',
        'bags_loaded' => 'integer',
        'oversized_pieces' => 'integer',
    ];

    /**
     * The job this checkpoint belongs to.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(JobOperation::class, 'job_id');
    }

    /**
     * The checkpoint library reference (optional).
     */
    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
    }

    /**
     * The user who completed this checkpoint.
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * The user who skipped this checkpoint.
     */
    public function skippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'skipped_by');
    }

    /**
     * The user who verified this checkpoint.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * The user who overrode this checkpoint.
     */
    public function overriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'overridden_by');
    }

    /**
     * Mark this checkpoint as done.
     */
    public function markAsDone(User $user, string $method = 'mobile', ?array $evidence = []): void
    {
        $completedAt = now();
        $actualDuration = null;
        $isOnTime = null;
        $delayMinutes = null;

        // Calculate duration
        // Priority 1: If checkpoint was started, use start time
        if ($this->started_at) {
            $actualDuration = $completedAt->diffInSeconds($this->started_at);
        }
        // Priority 2: If we have scheduled time and estimated minutes, use estimated duration
        elseif ($this->scheduled_at && $this->estimated_minutes) {
            // Use estimated minutes as the baseline duration
            $actualDuration = $this->estimated_minutes * 60;
        }
        // Priority 3: If we have scheduled time, calculate from scheduled to actual
        elseif ($this->scheduled_at) {
            $actualDuration = abs($completedAt->diffInSeconds($this->scheduled_at));
        }

        // Check if on time based on movement's window_end (not individual checkpoint scheduled_at)
        $movement = $this->job->movement;
        if ($movement && $movement->window_end) {
            $windowEnd = \Carbon\Carbon::parse($movement->window_end);
            $delayMinutes = $completedAt->diffInMinutes($windowEnd, false);
            // On-time if completed before or at window_end
            $isOnTime = $delayMinutes <= 0;
            // Only store positive delays (after window_end)
            $delayMinutes = max(0, -$delayMinutes);
        }

        $this->update([
            'state' => 'done',
            'completed_at' => $completedAt,
            'completed_by' => $user->id,
            'completion_method' => $method,
            'actual_duration_seconds' => $actualDuration,
            'is_on_time' => $isOnTime,
            'delay_minutes' => $delayMinutes,
            'photo_path' => $evidence['photo'] ?? null,
            'signature_path' => $evidence['signature'] ?? null,
            'gps_latitude' => $evidence['gps_latitude'] ?? null,
            'gps_longitude' => $evidence['gps_longitude'] ?? null,
            'location_name' => $evidence['location_name'] ?? null,
            'gps_accuracy_meters' => $evidence['gps_accuracy_meters'] ?? null,
            'notes' => $evidence['notes'] ?? null,
        ]);

        // Update job progress
        $this->job->updateProgress();
    }

    /**
     * Mark this checkpoint as skipped.
     */
    public function markAsSkipped(User $user, string $reason, ?string $exceptionType = null): void
    {
        $this->update([
            'state' => 'skipped',
            'skip_reason' => $reason,
            'skipped_by' => $user->id,
            'skipped_at' => now(),
            'exception_type' => $exceptionType,
        ]);

        // Update job progress
        $this->job->updateProgress();
    }

    /**
     * Start this checkpoint (mark as active).
     */
    public function start(): void
    {
        $this->update([
            'state' => 'active',
            'started_at' => now(),
        ]);
    }

    /**
     * Verify this checkpoint by a supervisor.
     */
    public function verify(User $user, ?string $verificationCode = null): void
    {
        $this->update([
            'verified_by' => $user->id,
            'verified_at' => now(),
            'verification_code' => $verificationCode,
        ]);
    }

    /**
     * Scope for pending checkpoints.
     */
    public function scopePending($query)
    {
        return $query->where('state', 'pending');
    }

    /**
     * Scope for completed checkpoints.
     */
    public function scopeCompleted($query)
    {
        return $query->where('state', 'done');
    }

    /**
     * Scope for active checkpoints.
     */
    public function scopeActive($query)
    {
        return $query->where('state', 'active');
    }

    /**
     * Scope for skipped checkpoints.
     */
    public function scopeSkipped($query)
    {
        return $query->where('state', 'skipped');
    }

    /**
     * Scope for overdue checkpoints.
     */
    public function scopeOverdue($query)
    {
        return $query->where('scheduled_at', '<', now())
            ->whereIn('state', ['pending', 'active']);
    }

    /**
     * Scope for delayed checkpoints.
     */
    public function scopeDelayed($query)
    {
        return $query->where('is_on_time', false)
            ->where('state', 'done');
    }

    /**
     * Scope for required checkpoints.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Check if this checkpoint is overdue.
     */
    public function isOverdue(): bool
    {
        if (!$this->scheduled_at || in_array($this->state, ['done', 'skipped'])) {
            return false;
        }

        return now()->isAfter($this->scheduled_at);
    }

    /**
     * Get the delay in minutes (negative if early, positive if late).
     */
    public function getDelayMinutesAttribute(): ?int
    {
        if (!$this->scheduled_at || !$this->completed_at) {
            return null;
        }

        return $this->completed_at->diffInMinutes($this->scheduled_at, false);
    }
}
