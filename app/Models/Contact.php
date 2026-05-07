<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $table = 'pma_contacts';

    protected $fillable = [
        'name',
        'role',
        'org',
        'phone',
        'on_shift',
        'disabled',
    ];

    protected $casts = [
        'on_shift' => 'boolean',
        'disabled' => 'boolean',
    ];

    /**
     * Scope: Only active (non-disabled) contacts
     */
    public function scopeActive($query)
    {
        return $query->where('disabled', 0);
    }

    /**
     * Scope: Only contacts currently on shift
     */
    public function scopeOnShift($query)
    {
        return $query->where('on_shift', 1);
    }
}
