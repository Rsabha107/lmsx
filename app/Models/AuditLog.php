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
    use Concerns\SerializesWallClock;

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

    /**
     * Audit Trail filters, shared by the page and its export: free text over who, role, action,
     * target and details; role; and From / To dates (inclusive, venue wall-clock).
     *
     * @param  array{search?: ?string, role?: ?string, from?: ?string, to?: ?string}  $filters
     */
    public function scopeFiltered($query, array $filters)
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $query
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                foreach (['user_name', 'user_role', 'action', 'target', 'meta'] as $column) {
                    $inner->orWhere($column, 'like', $like);
                }
            }))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('user_role', $role))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('created_at', '>=', $from.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('created_at', '<=', $to.' 23:59:59'));
    }

    /**
     * The IP and a readable version of `meta`: change() stores JSON (before/after pairs, counts,
     * the IP), record() stores plain text, which is returned as is.
     *
     * @return array{ip: ?string, details: string}
     */
    public function readableMeta(): array
    {
        $meta = (string) $this->meta;
        $data = json_decode($meta, true);
        if (! is_array($data)) {
            return ['ip' => null, 'details' => $meta];
        }

        $ip = isset($data['ip']) && is_scalar($data['ip']) ? (string) $data['ip'] : null;
        unset($data['ip']);

        $value = function ($v) use (&$value): string {
            if (is_bool($v)) {
                return $v ? 'yes' : 'no';
            }
            if ($v === null) {
                return '—';
            }
            if (is_array($v)) {
                return array_is_list($v)
                    ? implode(', ', array_map($value, $v))
                    : implode(', ', array_map(fn ($k, $x) => "{$k}: ".$value($x), array_keys($v), $v));
            }

            return (string) $v;
        };

        $parts = [];
        // Before/after pairs from AuditLog::changes(): "field: old → new".
        if (isset($data['from'], $data['to']) && is_array($data['from']) && is_array($data['to'])) {
            foreach ($data['to'] as $field => $new) {
                $parts[] = "{$field}: ".$value($data['from'][$field] ?? null).' → '.$value($new);
            }
            unset($data['from'], $data['to']);
        }
        foreach ($data as $key => $v) {
            $parts[] = "{$key}: ".$value($v);
        }

        return ['ip' => $ip, 'details' => implode('; ', $parts)];
    }
}
