<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An extra vehicle + driver on a movement; the lead pair stays on the movement itself. */
class MovementUnit extends Model
{
    protected $fillable = ['movement_id', 'vehicle_id', 'driver_id', 'position'];

    protected $casts = [
        'position' => 'integer',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
