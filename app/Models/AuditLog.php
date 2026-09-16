<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * A durable record of who did what, feeding the Audit Trail page.
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'user_role',
        'action',
        'target',
        'meta',
        'auditable_type',
        'auditable_id',
        'event_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Write an entry, attributing it to the authenticated user or to "System"
     * for anything triggered without a session (queues, automation).
     */
    public static function record(
        string $action,
        ?string $target = null,
        ?string $meta = null,
        ?Model $subject = null,
        ?int $eventId = null
    ): self {
        $user = Auth::user();
        $user = $user instanceof User ? $user : null;

        return self::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name ?? 'System',
            'user_role' => $user?->getRoleNames()->first() ?? 'Automation',
            'action' => $action,
            'target' => $target,
            'meta' => $meta,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'event_id' => $eventId,
        ]);
    }
}
