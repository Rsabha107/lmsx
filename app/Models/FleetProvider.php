<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetProvider extends Model
{
    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'phone',
        'email',
        'total_vehicles',
        'total_drivers',
        'rating',
        'status',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'total_vehicles' => 'integer',
        'total_drivers' => 'integer',
        'rating' => 'decimal:1',
        'is_active' => 'integer',
    ];

    /**
     * Scope a query to only include active providers.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include standby providers.
     */
    public function scopeStandby($query)
    {
        return $query->where('status', 'standby');
    }
}
