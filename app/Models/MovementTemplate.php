<?php

namespace App\Models;

use App\Models\Concerns\UppercasesCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MovementTemplate extends Model
{
    use Concerns\SerializesWallClock;
    // Deleted templates sit in the recycle bin (deletion_id) until the purge removes them.
    use SoftDeletes;

    use UppercasesCode;

    protected $fillable = [
        'event_id',
        'code',
        'name',
        'description',
        'scenario_type',
        'functional_area',
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
     * The event this template belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

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
     * Window length of a movement built from one of this template's legs: the template's
     * estimated duration for a one-leg template, else the leg's own or an even share of the
     * template's, else 30 minutes.
     */
    public function legMinutes(?MovementTemplateLeg $leg): int
    {
        return $this->estimatedLegMinutes($leg) ?? 30;
    }

    /** As legMinutes, but null when neither the template nor the leg has a duration. */
    public function estimatedLegMinutes(?MovementTemplateLeg $leg): ?int
    {
        $count = max(1, $this->legs->count());

        if ($count === 1 && $this->estimated_duration_minutes) {
            return $this->estimated_duration_minutes;
        }

        return $leg?->estimated_duration_minutes
            ?: ($this->estimated_duration_minutes ? (int) round($this->estimated_duration_minutes / $count) : null);
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
