<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamFlight extends Model
{
    protected $fillable = [
        'event_id',
        'team_id',
        'direction',
        'flight_number',
        'origin_airport_id',
        'destination_airport_id',
        'terminal',
        'gate',
        'scheduled_at',
        'estimated_at',
        'actual_at',
        'delay_minutes',
        'flight_status',
        'flight_synced_at',
        'manifest',
        'party_size_total',
        'party_size_players',
        'party_size_staff',
        'notes',
    ];

    protected $casts = [
        'scheduled_at'        => 'datetime:Y-m-d H:i:s',
        'estimated_at'        => 'datetime:Y-m-d H:i:s',
        'actual_at'           => 'datetime:Y-m-d H:i:s',
        'flight_synced_at'    => 'datetime:Y-m-d H:i:s',
        'manifest'            => 'array',
        'delay_minutes'       => 'integer',
        'party_size_total'    => 'integer',
        'party_size_players'  => 'integer',
        'party_size_staff'    => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function originAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'origin_airport_id');
    }

    public function destinationAirport(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'destination_airport_id');
    }

    public function scopeArrival($query)
    {
        return $query->where('direction', 'arrival');
    }

    public function scopeDeparture($query)
    {
        return $query->where('direction', 'departure');
    }
}
