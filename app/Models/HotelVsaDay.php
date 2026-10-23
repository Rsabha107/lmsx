<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The crew teams working at the Aspire VSA on one day. */
class HotelVsaDay extends Model
{
    use Concerns\SerializesWallClock;

    protected $fillable = ['event_id', 'date', 'crews'];

    protected $casts = [
        'date' => 'date',
        'crews' => 'array',
    ];
}
