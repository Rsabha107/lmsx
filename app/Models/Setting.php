<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    protected $table = 'pma_settings';

    protected $fillable = [
        'key',
        'value',
        'scope',
        'scope_id',
        'checkpoint_id',
        'description',
    ];

    protected $casts = [
        'scope_id' => 'integer',
        'checkpoint_id' => 'integer',
    ];

    /**
     * Scope constants for type safety
     */
    const SCOPE_GLOBAL = 'global';
    const SCOPE_EVENT = 'event';
    const SCOPE_TEMPLATE = 'template';

    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(Checkpoint::class);
    }
}
