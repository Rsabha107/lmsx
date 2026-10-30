<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamFlight extends Model
{
    use Concerns\SerializesWallClock;

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
        'planned_bags',
        'notes',
    ];

    protected $casts = [
        'scheduled_at'        => 'datetime:Y-m-d H:i:s',
        'estimated_at'        => 'datetime:Y-m-d H:i:s',
        'actual_at'           => 'datetime:Y-m-d H:i:s',
        'flight_synced_at'    => 'datetime:Y-m-d H:i:s',
        'flight_sync_attempted_at' => 'datetime:Y-m-d H:i:s',
        'manifest'            => 'array',
        'delay_minutes'       => 'integer',
        'party_size_total'    => 'integer',
        'party_size_players'  => 'integer',
        'party_size_staff'    => 'integer',
        'planned_bags'        => 'integer',
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

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class, 'flight_id');
    }

    /** Job ids generated from this flight's movements: while there are any, the flight can't be deleted. */
    public function issuedJobIds(): array
    {
        return $this->movements()->whereNotNull('job_id')->orderBy('job_id')->pluck('job_id')->all();
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
