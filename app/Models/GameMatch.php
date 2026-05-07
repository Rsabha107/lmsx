<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameMatch extends Model
{
    protected $table = 'matches';

    protected $fillable = [
        'event',
        'venue',
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
     * Get the first team.
     */
    public function team1()
    {
        return $this->belongsTo(Team::class, 'team1_id', 'code');
    }

    /**
     * Get the second team.
     */
    public function team2()
    {
        return $this->belongsTo(Team::class, 'team2_id', 'code');
    }
}
