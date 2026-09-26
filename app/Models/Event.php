<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    protected $fillable = [
        'name',
        'short_name',
        'event_logo',
        'host_country',
        'start_date',
        'end_date',
        'active_flag',
        'notes',
    ];

    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_CURRENT = 'current';
    public const STATUS_PAST = 'past';
    public const STATUS_CANCELLED = 'cancelled';

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'active_flag' => 'boolean',
    ];

    protected $appends = ['logo_url', 'status'];

    /**
     * Dates decide where an event sits; active_flag = false means it was
     * cancelled, whatever its dates say.
     */
    public function getStatusAttribute(): string
    {
        if (! $this->active_flag) {
            return self::STATUS_CANCELLED;
        }

        $today = today();

        if (! $this->start_date || $today->lt($this->start_date)) {
            return self::STATUS_UPCOMING;
        }

        if ($this->end_date && $today->gt($this->end_date)) {
            return self::STATUS_PAST;
        }

        return self::STATUS_CURRENT;
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (blank($this->event_logo)) {
            return null;
        }

        // Root-relative on purpose. Storage::url() prefixes APP_URL, which breaks
        // the image on any other host or port, and would emit an http:// URL
        // behind a TLS-terminating proxy - blocked as mixed content.
        return '/storage/' . ltrim($this->event_logo, '/');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'host_country', 'country_code');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'event_venue')
                    ->withPivot('purpose', 'notes')
                    ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_events')->withTimestamps();
    }

    /** Not cancelled. */
    public function scopeActive($query)
    {
        return $query->where('active_flag', true);
    }

    /** The event running today, else the latest one that isn't cancelled. */
    public static function defaultId(): ?int
    {
        return static::active()->current()->orderByDesc('start_date')->value('id')
            ?: static::active()->latest('start_date')->latest('id')->value('id');
    }

    /** Running today (dates only, not the cancelled flag). */
    public function scopeCurrent($query)
    {
        $today = today()->toDateString();

        return $query->whereDate('start_date', '<=', $today)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $today));
    }
}
