<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One delete action in the recycle bin: what was asked for, by whom, whether a second admin
 * had to approve it, what was actually deleted, and its restore / purge state.
 */
class Deletion extends Model
{
    use Concerns\SerializesWallClock;

    public const KIND_LABELS = [
        'jobs' => ['job', 'jobs'],
        'plans' => ['plan', 'plans'],
        'movements' => ['movement', 'movements'],
        'movement_templates' => ['movement template', 'movement templates'],
        'checkpoint_templates' => ['checkpoint template', 'checkpoint templates'],
        'event' => ['event', 'events'],
    ];

    /** Still in the bin: can be restored, and will be purged. */
    public const IN_BIN = ['done', 'partially_restored'];

    protected $fillable = [
        'event_id', 'kind', 'status', 'trigger',
        'requested_by', 'requester_name', 'approved_by', 'approver_name',
        'reason', 'decision_note', 'request', 'items', 'summary', 'item_count',
        'expires_at', 'decided_at', 'executed_at', 'purge_after',
        'restored_by', 'restored_at', 'purged_at',
    ];

    protected $casts = [
        'request' => 'array',
        'items' => 'array',
        'summary' => 'array',
        'item_count' => 'integer',
        'expires_at' => 'datetime',
        'decided_at' => 'datetime',
        'executed_at' => 'datetime',
        'purge_after' => 'datetime',
        'restored_at' => 'datetime',
        'purged_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public static function label(string $kind, int $count): string
    {
        [$one, $many] = self::KIND_LABELS[$kind] ?? [$kind, $kind];

        return $count.' '.($count === 1 ? $one : $many);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && (! $this->expires_at || $this->expires_at->isFuture());
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
