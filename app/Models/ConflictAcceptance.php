<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A planner's decision to live with a detected conflict (e.g. approved split
 * duty). Keyed by the detector's deterministic conflict id.
 */
class ConflictAcceptance extends Model
{
    protected $fillable = ['event_id', 'conflict_id', 'reason', 'user_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
