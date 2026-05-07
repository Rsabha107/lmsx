<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'date',
        'status',
        'movement_template_id',
        'notes',
        'movements_count',
        'teams_count',
        'created_by',
    ];

    protected $casts = [
        'date' => 'datetime',
        'movements_count' => 'integer',
        'teams_count' => 'integer',
    ];

    /**
     * The movement template this plan is based on (optional).
     */
    public function movementTemplate(): BelongsTo
    {
        return $this->belongsTo(MovementTemplate::class);
    }

    /**
     * The movements in this plan.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    /**
     * The user who created this plan.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope for active plans.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for plans on a specific date.
     */
    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Scope for plans by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
