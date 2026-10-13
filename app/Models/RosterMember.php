<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RosterMember extends Model
{
    protected $fillable = ['event_id', 'user_id', 'designation', 'sort'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
