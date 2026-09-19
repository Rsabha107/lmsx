<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function functionalAreas(): HasMany
    {
        return $this->hasMany(UserFunctionalArea::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'user_events')->withTimestamps();
    }

    /**
     * Admins and holders of events.access-all reach every event. Everyone else
     * is limited to their assignments, and no assignments means no access.
     */
    public function canAccessEvent(?int $eventId): bool
    {
        if (! $eventId) {
            return false;
        }

        if ($this->hasRole('admin') || $this->can('events.access-all')) {
            return true;
        }

        return $this->events->pluck('id')->contains($eventId);
    }

    /**
     * @return array<int, int>
     */
    public function accessibleEventIds(): array
    {
        return $this->events->pluck('id')->all();
    }

    public function canAccessAllEvents(): bool
    {
        return $this->hasRole('admin') || $this->can('events.access-all');
    }

    public function hasFunctionalArea(?string $area): bool
    {
        if (! $area) {
            return false;
        }

        return in_array($area, $this->functionalAreaCodes(), true);
    }

    /**
     * @return array<int, string>
     */
    public function functionalAreaCodes(): array
    {
        return $this->functionalAreas->pluck('functional_area')->all();
    }
}
