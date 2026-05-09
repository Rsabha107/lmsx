<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EventTeam extends Model
{
    protected $fillable = [
        'event_id',
        'team_id',
        'group_pool',
        'classification_type_id',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(TeamClassification::class, 'classification_type_id');
    }

    public function flights(): HasMany
    {
        return $this->hasMany(TeamFlight::class, 'team_id', 'team_id');
    }

    public function stay(): HasOne
    {
        return $this->hasOne(TeamStay::class, 'team_id', 'team_id');
    }
}
