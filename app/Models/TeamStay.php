<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamStay extends Model
{
    protected $fillable = [
        'event_id',
        'team_id',
        'hotel_name',
        'address',
        'training_ground',
        'check_in',
        'check_out',
        'room_count',
        'notes',
    ];

    protected $casts = [
        'check_in'   => 'date',
        'check_out'  => 'date',
        'room_count' => 'integer',
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
