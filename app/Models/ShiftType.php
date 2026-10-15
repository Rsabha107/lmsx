<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftType extends Model
{
    use Concerns\SerializesWallClock;

    protected $fillable = ['name', 'code', 'color', 'start_time', 'end_time', 'is_leave', 'sort'];

    protected $casts = [
        'is_leave' => 'boolean',
        'sort' => 'integer',
    ];

    /** "09:00–17:00", or null when the shift has no set hours. */
    public function hours(): ?string
    {
        return $this->start_time && $this->end_time
            ? substr($this->start_time, 0, 5) . '–' . substr($this->end_time, 0, 5)
            : null;
    }
}
