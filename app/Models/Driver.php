<?php

namespace App\Models;

use App\Models\Scopes\ProviderScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Driver extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new ProviderScope);
    }

    /** Drivers put forward for an event; only they are offered for its movements. */
    public function scopeInEventPool($query, ?int $eventId)
    {
        return $query->whereIn('drivers.id', DB::table('event_driver')->where('event_id', $eventId)->select('driver_id'));
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_driver')->withTimestamps();
    }

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

    public function movements(): HasMany
    {
        return $this->hasMany(Movement::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(JobOperation::class);
    }

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
        return $this->belongsTo(FleetProvider::class, 'provider_id')->withoutGlobalScopes();
    }
}
