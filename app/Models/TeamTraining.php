<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamTraining extends Model
{
    protected $fillable = [
        'event_id',
        'team_id',
        'training_ground',
        'training_start_at',
        'notes',
    ];

    protected $casts = [
        'training_start_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }
}
