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

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'host_country', 'country_code');
    }

    public function eventTeams(): HasMany
    {
        return $this->hasMany(EventTeam::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'event_teams', 'event_id', 'team_code', 'id', 'code')
                    ->withPivot('group_pool', 'classification_type_id')
                    ->withTimestamps();
    }

    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'event_venue')
                    ->withPivot('purpose', 'notes')
                    ->withTimestamps();
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
