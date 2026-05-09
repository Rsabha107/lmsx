<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city',
        'country_code',
        'capacity',
        'address',
        'type',
        'notes',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'country_code');
    }
}
