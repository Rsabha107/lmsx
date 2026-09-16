<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobIssue extends Model
{
    /** Report reasons offered by the mobile app, keyed by severity. */
    public const TYPES = [
        'vehicle_breakdown' => 'danger',
        'traffic_delay' => 'warn',
        'passenger_issue' => 'warn',
        'access_denied' => 'danger',
        'other' => 'warn',
    ];

    protected $fillable = [
        'job_id',
        'event_id',
        'reported_by',
        'type',
        'severity',
        'notes',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobOperation::class, 'job_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function label(): string
    {
        return ucfirst(str_replace('_', ' ', $this->type));
    }
}
