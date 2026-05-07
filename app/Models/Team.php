<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'team_name',
        'country_id',
        'flag',
        'group_pool',
        'classification_type_id',
        'party_size_total',
        'party_size_players',
        'party_size_staff',
        'hotel_name',
        'training_ground',
        'origin_airport_id',
        'destination_airport_id',
        'gate',
        'flight_number',
        'arrival_date_time',
        'departure_date_time',
        'arrival_manifest',
        'head_of_delegation',
        'sc_liaison_name',
        'sc_liaison_phone',
        'bib_accent_color',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'arrival_date_time' => 'datetime',
        'departure_date_time' => 'datetime',
        'arrival_manifest' => 'array',
        'is_active' => 'boolean',
        'party_size_total' => 'integer',
        'party_size_players' => 'integer',
        'party_size_staff' => 'integer',
        'classification_type_id' => 'integer',
    ];

    /**
     * Get the classification that owns the team.
     */
    public function classification()
    {
        return $this->belongsTo(TeamClassification::class, 'classification_type_id');
    }

    /**
     * Get the country that owns the team.
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id', 'country_code');
    }

    /**
     * Get the origin airport.
     */
    public function originAirport()
    {
        return $this->belongsTo(Airport::class, 'origin_airport_id');
    }

    /**
     * Get the destination airport.
     */
    public function destinationAirport()
    {
        return $this->belongsTo(Airport::class, 'destination_airport_id');
    }

    /**
     * Scope a query to only include active teams.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get teams by group/pool.
     */
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group_pool', $group);
    }
}
