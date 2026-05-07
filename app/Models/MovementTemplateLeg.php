<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovementTemplateLeg extends Model
{
    protected $fillable = [
        'movement_template_id',
        'checkpoint_template_id',
        'order',
        'name',
        'leg_type',
        'from_location',
        'to_location',
        'estimated_duration_minutes',
        'vehicle_type',
        'estimated_passengers',
    ];

    protected $casts = [
        'order' => 'integer',
        'estimated_duration_minutes' => 'integer',
        'estimated_passengers' => 'integer',
    ];

    /**
     * The movement template this leg belongs to.
     */
    public function movementTemplate(): BelongsTo
    {
        return $this->belongsTo(MovementTemplate::class);
    }

    /**
     * The checkpoint template (sequence) for this leg.
     */
    public function checkpointTemplate(): BelongsTo
    {
        return $this->belongsTo(CheckpointTemplate::class);
    }
}
