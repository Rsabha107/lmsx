<?php

namespace App\Models;

use App\Models\Scopes\ProviderScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Vehicle extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new ProviderScope);
    }

    /** Vehicles put forward for an event; only they are offered for its movements. */
    public function scopeInEventPool($query, ?int $eventId)
    {
        return $query->whereIn('vehicles.id', DB::table('event_vehicle')->where('event_id', $eventId)->select('vehicle_id'));
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_vehicle')->withTimestamps();
    }

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
        return $this->belongsTo(FleetProvider::class, 'provider_id')->withoutGlobalScopes();
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
