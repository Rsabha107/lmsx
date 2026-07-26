<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'event_id',
        'venue_id',
        'match_number',
        'team1_id',
        'team2_id',
        'stage',
        'match_date',
        'gates_opening',
        'kick_off',
    ];

    protected $casts = [
        'match_date' => 'datetime',
        'gates_opening' => 'datetime',
        'kick_off' => 'datetime',
    ];

    /**
     * Get the event.
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the venue.
     */
    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Get the first team.
     */
    public function team1()
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    /**
     * Get the second team.
     */
    public function team2()
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }
}
