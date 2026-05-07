<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airport extends Model
{
    protected $fillable = [
        'code',
        'name',
        'city',
        'country',
    ];

    // Teams that have this airport as origin
    public function teamsAsOrigin()
    {
        return $this->hasMany(Team::class, 'origin_airport_id');
    }

    // Teams that have this airport as destination
    public function teamsAsDestination()
    {
        return $this->hasMany(Team::class, 'destination_airport_id');
    }
}
