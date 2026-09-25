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
        'status',
        'active_flag',
        'notes',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'active_flag' => 'boolean',
    ];

    protected $appends = ['logo_url'];

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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
