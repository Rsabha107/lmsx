<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'code',
        'provider_id',
        'plate_number',
        'vehicle_type',
        'capacity',
        'fuel_level',
        'status',
        'is_active',
        'notes',
        'category',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'is_active' => 'integer',
        'provider_id' => 'integer',
    ];

    // Scopes for filtering
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeOnJob($query)
    {
        return $query->where('status', 'on_job');
    }

    public function scopeMaintenance($query)
    {
        return $query->where('status', 'maintenance');
    }

    public function scopeStandby($query)
    {
        return $query->where('status', 'standby');
    }
}
