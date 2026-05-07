<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MovementTemplate extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'scenario_type',
        'total_legs',
        'estimated_duration_minutes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'total_legs' => 'integer',
        'estimated_duration_minutes' => 'integer',
    ];

    /**
     * The legs (movements) in this template.
     */
    public function legs(): HasMany
    {
        return $this->hasMany(MovementTemplateLeg::class)->orderBy('order');
    }

    /**
     * Plans that were created from this template.
     */
    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class);
    }

    /**
     * Scope for active templates only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by scenario type.
     */
    public function scopeForScenario($query, string $scenario)
    {
        return $query->where('scenario_type', $scenario);
    }
}
