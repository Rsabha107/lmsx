<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An emailed invitation to create an account. The short link carries a random code; only its
 * sha256 is stored, so the table never holds a usable link.
 */
class UserInvitation extends Model
{
    use Concerns\SerializesWallClock;

    public const DEFAULT_DAYS = 7;

    protected $fillable = [
        'email', 'name', 'role_ids', 'event_ids', 'fleet_provider_id', 'code_hash', 'invited_by',
        'expires_at', 'accepted_at', 'accepted_user_id', 'revoked_at', 'sent_count', 'last_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'role_ids' => 'array',
            'event_ids' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    public static function newCode(): string
    {
        return Str::random(12);
    }

    public static function hashCode(string $code): string
    {
        return hash('sha256', $code);
    }

    public static function findByCode(string $code): ?self
    {
        return preg_match('/^[A-Za-z0-9]{12}$/', $code) ? static::where('code_hash', static::hashCode($code))->first() : null;
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    /** pending | accepted | expired | revoked */
    public function status(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at?->isPast() ?? false => 'expired',
            default => 'pending',
        };
    }

    public function isPending(): bool
    {
        return $this->status() === 'pending';
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
