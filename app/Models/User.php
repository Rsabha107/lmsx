<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'job_title', 'password', 'provider', 'provider_id', 'fleet_provider_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /** Holds access.manage: the only role that can open or change Roles & Permissions. */
    public const SECURITY_ROLE = 'SecurityRole';

    public const ACCESS_PERMISSION = 'access.manage';

    /** Code looks these up by name, so they can be neither deleted nor renamed. */
    public const PROTECTED_ROLES = ['admin', self::SECURITY_ROLE];

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

    public function fleetProvider(): BelongsTo
    {
        return $this->belongsTo(FleetProvider::class, 'fleet_provider_id')->withoutGlobalScopes();
    }

    /** Agencies, and anyone tied to a provider, work only on that provider's movements and fleet. */
    public function isProviderRestricted(): bool
    {
        if ($this->hasRole('admin')) {
            return false;
        }

        return $this->fleet_provider_id !== null || $this->hasRole('agency');
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
     * Who can be put on a movement as a supervisor: the field supervisors (ground_control), and only
     * the viewer's own provider's people when the viewer is provider-restricted.
     */
    public function scopeFieldSupervisors($query, ?self $viewer = null)
    {
        $viewer ??= Auth::user();

        return $query->whereHas('roles', fn ($q) => $q->where('name', 'ground_control'))
            ->when($viewer?->isProviderRestricted(), fn ($q) => $q->where('users.fleet_provider_id', $viewer->fleet_provider_id ?? 0));
    }

    /**
     * @return array<int, string>
     */
    public function functionalAreaCodes(): array
    {
        return $this->functionalAreas->pluck('functional_area')->all();
    }
}
