<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One team hotel on one match day: its supervisor and the kit runs to and from the venue. */
class HotelDutyDay extends Model
{
    use Concerns\SerializesWallClock;

    protected $fillable = [
        'event_id', 'hotel', 'date', 'supervisor_id',
        'departure_crews', 'departure_time', 'arrival_crews', 'arrival_time',
    ];

    protected $casts = [
        'date' => 'date',
        'departure_crews' => 'array',
        'arrival_crews' => 'array',
    ];

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
