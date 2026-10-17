<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone that receives push notifications for a signed-in user.
 */
class UserDevice extends Model
{
    protected $fillable = [
        'user_id',
        'personal_access_token_id',
        'token',
        'token_hash',
        'platform',
        'app_version',
        'last_seen_at',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
