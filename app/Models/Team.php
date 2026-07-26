<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'code',
        'team_name',
        'country_id',
        'flag',
        'group_pool',
        'classification_type_id',
        'origin_airport_id',
        'destination_airport_id',
        'gate',
        'arrival_manifest',
        'head_of_delegation',
        'bib_accent_color',
        'notes',
        'is_active',
        'flight_synced_at',
    ];

    protected $casts = [
        'arrival_manifest' => 'array',
        'is_active' => 'boolean',
        'flight_synced_at' => 'datetime',
        'classification_type_id' => 'integer',
    ];

    /**
     * Get the event that owns the team.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the classification that owns the team.
     */
    public function classification()
    {
        return $this->belongsTo(TeamClassification::class, 'classification_type_id');
    }

    /**
     * Get the country that owns the team.
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'country_code');
    }

    /**
     * Get the origin airport.
     */
    public function originAirport()
    {
        return $this->belongsTo(Airport::class, 'origin_airport_id');
    }

    /**
     * Get the destination airport.
     */
    public function destinationAirport()
    {
        return $this->belongsTo(Airport::class, 'destination_airport_id');
    }

    public function flights(): HasMany
    {
        return $this->hasMany(TeamFlight::class, 'team_id');
    }

    public function stays(): HasMany
    {
        return $this->hasMany(TeamStay::class, 'team_id');
    }

    /**
     * Get the current/latest stay for this team (for event-specific context).
     */
    public function stay(): HasOne
    {
        return $this->hasOne(TeamStay::class, 'team_id')->latestOfMany();
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(TeamTraining::class, 'team_id');
    }

    /**
     * Get the current/latest training info for this team (for event-specific context).
     */
    public function training(): HasOne
    {
        return $this->hasOne(TeamTraining::class, 'team_id')->latestOfMany();
    }

    /**
     * Scope a query to only include active teams.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get teams by group/pool.
     */
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group_pool', $group);
    }
}
