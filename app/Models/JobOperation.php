<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobOperation extends Model
{
    protected $table = 'jobs_operations';

    protected $fillable = [
        'job_id',
        'movement_id',
        'plan_id',
        'team_id',
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
