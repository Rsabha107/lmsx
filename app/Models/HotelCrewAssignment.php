<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** The numbered crew team that handles one team's kit for one match at its hotel. */
class HotelCrewAssignment extends Model
{
    use Concerns\SerializesWallClock;

    protected $fillable = ['event_id', 'match_id', 'team_id', 'crew_team'];

    protected $casts = [
        'crew_team' => 'integer',
    ];
}
