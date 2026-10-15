<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterShift extends Model
{
    use Concerns\SerializesWallClock;

    protected $fillable = ['event_id', 'user_id', 'date', 'shift_type_id'];

    protected $casts = [
        'date' => 'date',
    ];

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ShiftType::class);
    }
}
