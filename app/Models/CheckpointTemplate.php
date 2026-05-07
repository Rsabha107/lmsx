<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CheckpointTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'movement_type',
        'description',
        'estimated_duration_minutes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'estimated_duration_minutes' => 'integer',
    ];

    /**
     * The checkpoints in this template (ordered sequence).
     */
    public function checkpoints(): BelongsToMany
    {
        return $this->belongsToMany(Checkpoint::class, 'checkpoint_checkpoint_template')
            ->withPivot(['order', 'is_required', 'estimated_minutes'])
            ->withTimestamps()
            ->orderBy('pivot_order');
    }

    /**
     * Movements that use this checkpoint template.
     */
    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    /**
     * Movement template legs that use this checkpoint template.
     */
    public function movementTemplateLegs(): HasMany
    {
        return $this->hasMany(MovementTemplateLeg::class);
    }

    /**
     * Get total number of checkpoints in this template.
     */
    public function getCheckpointCountAttribute(): int
    {
        return $this->checkpoints()->count();
    }

    /**
     * Scope for active templates only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by movement type.
     */
    public function scopeForMovementType($query, string $type)
    {
        return $query->where('movement_type', $type);
    }
}
