<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BaseCampHotel extends Model
{
    protected $table = 'base_camp_hotels';

    protected $fillable = [
        'name',
        'disabled',
    ];

    protected $casts = [
        'disabled' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('disabled', 0);
    }
}
