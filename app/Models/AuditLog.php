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
     * Old and new values of the attributes a save just changed. Capture $model->getOriginal()
     * before the update and pass it in.
     *
     * @param  array<string, mixed>  $original
     * @return array{from: array<string, mixed>, to: array<string, mixed>}
     */
    public static function changes(Model $model, array $original): array
    {
        $to = array_diff_key($model->getChanges(), ['updated_at' => true, 'password' => true, 'remember_token' => true]);

        return ['from' => array_intersect_key($original, $to), 'to' => $to];
    }

    /**
     * For privileged or configuration changes: who did it, from where, and a short summary
     * (before/after values, never secrets) stored as JSON in meta.
     *
     * @param  array<string, mixed>  $summary
     */
    public static function change(
        string $action,
        ?string $target = null,
        array $summary = [],
        ?Model $subject = null,
        ?int $eventId = null
    ): self {
        $summary['ip'] = request()->ip();

        return self::record(
            action: $action,
            target: $target,
            meta: json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            subject: $subject,
            eventId: $eventId,
        );
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
