<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInteraction extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'event_id',
        'question',
        'tool_calls',
        'response_summary',
        'status',
        'error_message',
        'duration_ms',
        'provider',
        'model',
    ];

    protected $casts = [
        'tool_calls' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
