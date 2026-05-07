<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamClassification extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the teams with this classification.
     */
    public function teams()
    {
        return $this->hasMany(Team::class, 'classification_type_id');
    }

    /**
     * Scope a query to only include active classifications.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
