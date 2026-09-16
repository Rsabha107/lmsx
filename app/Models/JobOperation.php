<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobOperation extends Model
{
    protected $table = 'jobs_operations';

    public const STATUSES = ['pending', 'dispatched', 'in-progress', 'completed', 'cancelled'];

    /**
     * Which statuses a job may move to from its current one. A job only ever
     * moves forwards, except that anything unfinished can be cancelled and a
     * job started by mistake can be put back to pending.
     */
    public const TRANSITIONS = [
        'pending' => ['dispatched', 'in-progress', 'cancelled'],
        'dispatched' => ['in-progress', 'cancelled'],
        'in-progress' => ['completed', 'cancelled', 'pending'],
        'completed' => [],
        'cancelled' => [],
    ];

    /** The timestamp column stamped when a job enters a given status. */
    public const STATUS_TIMESTAMPS = [
        'dispatched' => 'dispatched_at',
        'in-progress' => 'started_at',
        'completed' => 'completed_at',
    ];

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    protected $fillable = [
        'job_id',
        'event_id',
        'movement_id',
        'plan_id',
        'team_id',
        'functional_area',
        'supervisor_id',
        'driver_id',
        'vehicle_id',
        'status',
        'checkpoints_completed',
        'checkpoints_total',
        'progress_percentage',
        'dispatched_at',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'checkpoints_completed' => 'integer',
        'checkpoints_total' => 'integer',
        'progress_percentage' => 'decimal:2',
    ];

    /**
     * The event this job belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The movement this job was generated from.
     */
    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    /**
     * The plan this job belongs to.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The team for this job.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The supervisor assigned to this job.
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * The driver assigned to this job.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * The vehicle assigned to this job.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * The checkpoints for this job (snapshotted from template).
     */
    public function checkpoints(): HasMany
    {
        return $this->hasMany(JobCheckpoint::class, 'job_id')->orderBy('order');
    }

    /**
     * Field-reported problems, newest first.
     */
    public function issues(): HasMany
    {
        return $this->hasMany(JobIssue::class, 'job_id')->latest();
    }

    /**
     * Update progress based on completed checkpoints.
     */
    public function updateProgress(): void
    {
        $completed = $this->checkpoints()->where('state', 'done')->count();
        $total = $this->checkpoints()->count();

        $this->update([
            'checkpoints_completed' => $completed,
            'checkpoints_total' => $total,
            'progress_percentage' => $total > 0 ? ($completed / $total) * 100 : 0,
        ]);
    }

    /**
     * Scope for active jobs.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['dispatched', 'in-progress']);
    }
}
