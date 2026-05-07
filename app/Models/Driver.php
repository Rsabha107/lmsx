<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'provider_id',
        'name',
        'phone',
        'license_number',
        'status',
    ];

    protected $casts = [
        'provider_id' => 'integer',
    ];

    /**
     * Scope a query to only include available drivers.
     */
    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    /**
     * Scope a query to only include drivers on shift.
     */
    public function scopeOnShift($query)
    {
        return $query->where('status', 'on_shift');
    }

    /**
     * Scope a query to only include off-duty drivers.
     */
    public function scopeOff($query)
    {
        return $query->where('status', 'off');
    }

    /**
     * Scope a query to only include resting drivers.
     */
    public function scopeRest($query)
    {
        return $query->where('status', 'rest');
    }

    /**
     * Get the provider that owns the driver.
     */
    public function provider()
    {
        return $this->belongsTo(FleetProvider::class, 'provider_id');
    }
}
