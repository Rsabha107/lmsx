<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Checkpoint extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'description',
        'capture_method',
        'requires_photo',
        'requires_signature',
        'is_active',
    ];

    protected $casts = [
        'requires_photo' => 'boolean',
        'requires_signature' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * The checkpoint templates that use this checkpoint.
     */
    public function checkpointTemplates(): BelongsToMany
    {
        return $this->belongsToMany(CheckpointTemplate::class, 'checkpoint_checkpoint_template')
            ->withPivot(['order', 'is_required', 'estimated_minutes'])
            ->withTimestamps()
            ->orderBy('pivot_order');
    }

    /**
     * Scope for active checkpoints only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
