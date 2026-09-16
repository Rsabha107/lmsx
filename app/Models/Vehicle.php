<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function provider(): BelongsTo
    {
        return $this->belongsTo(FleetProvider::class, 'provider_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(JobOperation::class);
    }

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
