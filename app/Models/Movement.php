<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\GameMatch;

class Movement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'plan_id',
        'team_id',
        'checkpoint_template_id',
        'kind',
        'from_location',
        'to_location',
        'window_start',
        'window_end',
        'actual_departure',
        'actual_arrival',
        'vehicle_id',
        'driver_id',
        'field_supervisor_id',
        'passengers',
        'status',
        'delay_minutes',
        'source',
        'flight_number',
        'match_id',
        'job_id',
        'job_generated_at',
        'notes',
    ];

    protected $casts = [
        'window_start' => 'datetime',
        'window_end' => 'datetime',
        'actual_departure' => 'datetime',
        'actual_arrival' => 'datetime',
        'job_generated_at' => 'datetime',
        'passengers' => 'integer',
        'delay_minutes' => 'integer',
    ];

    /**
     * The plan this movement belongs to.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * The team for this movement.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The checkpoint template (sequence) for this movement.
     */
    public function checkpointTemplate(): BelongsTo
    {
        return $this->belongsTo(CheckpointTemplate::class);
    }

    /**
     * The vehicle assigned to this movement.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * The driver assigned to this movement.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * The field supervisor assigned to this movement.
     */
    public function fieldSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'field_supervisor_id');
    }

    /**
     * The match this movement is for.
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    /**
     * The generated job for this movement.
     */
    public function job(): HasOne
    {
        return $this->hasOne(JobOperation::class);
    }

    /**
     * Check if this movement has been generated into a job.
     */
    public function hasJob(): bool
    {
        return !is_null($this->job_id);
    }

    /**
     * Scope for movements without jobs.
     */
    public function scopeWithoutJob($query)
    {
        return $query->whereNull('job_id');
    }

    /**
     * Scope for movements with jobs.
     */
    public function scopeWithJob($query)
    {
        return $query->whereNotNull('job_id');
    }

    /**
     * Scope by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
