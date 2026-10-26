<?php

namespace App\Services;

use App\Mail\AccessGrantedMail;
use App\Mail\UserInvitationMail;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/** Activating users, inviting new ones and granting SC/LOC staff access. */
class UserAccessService
{
    /** Switches a user on or off. Off also ends their sessions and mobile tokens straight away. */
    public function setActive(User $user, bool $active, User $actor): void
    {
        if ((bool) $user->is_active === $active) {
            return;
        }

        $user->forceFill(['is_active' => $active, 'deactivated_at' => $active ? null : now()])->save();

        if (! $active) {
            $user->tokens()->delete();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        }

        AuditLog::change($active ? 'User activated' : 'User deactivated', $user->email, ['active' => $active], $user);
    }

    /**
     * @param  array{email: string, name?: ?string, role_ids: array<int>, event_ids?: array<int>, fleet_provider_id?: ?int, expires_in_days?: ?int}  $data
     */
    public function invite(array $data, User $actor): UserInvitation
    {
        $email = Str::lower(trim($data['email']));

        // A fresh invitation replaces any link still open for the same address.
        UserInvitation::where('email', $email)->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $code = UserInvitation::newCode();
        $invitation = UserInvitation::create([
            'email' => $email,
            'name' => $data['name'] ?? null,
            'role_ids' => array_values(array_map('intval', $data['role_ids'])),
            'event_ids' => array_values(array_map('intval', $data['event_ids'] ?? [])),
            'fleet_provider_id' => $data['fleet_provider_id'] ?? null,
            'code_hash' => UserInvitation::hashCode($code),
            'invited_by' => $actor->id,
            'expires_at' => now()->addDays((int) ($data['expires_in_days'] ?? UserInvitation::DEFAULT_DAYS)),
            'last_sent_at' => now(),
        ]);

        $this->sendInvitation($invitation, $code, $actor);
        AuditLog::change('User invited', $email, $this->accessSummary($invitation->role_ids, $invitation->event_ids) + ['expires_at' => $invitation->expires_at->format('Y-m-d H:i')]);

        return $invitation;
    }

    /** A new code and expiry; the previous link stops working. */
    public function resend(UserInvitation $invitation, User $actor): void
    {
        $code = UserInvitation::newCode();
        $invitation->update([
            'code_hash' => UserInvitation::hashCode($code),
            'expires_at' => now()->addDays(UserInvitation::DEFAULT_DAYS),
            'revoked_at' => null,
            'sent_count' => $invitation->sent_count + 1,
            'last_sent_at' => now(),
        ]);

        $this->sendInvitation($invitation, $code, $actor);
        AuditLog::change('Invitation resent', $invitation->email, ['expires_at' => $invitation->expires_at->format('Y-m-d H:i')]);
    }

    public function revoke(UserInvitation $invitation): void
    {
        $invitation->update(['revoked_at' => now()]);
        AuditLog::change('Invitation revoked', $invitation->email, []);
    }

    /** The short link in the email; it opens the signed, expiring accept page. */
    public static function shortLink(string $code): string
    {
        return url('/i/'.$code);
    }

    public static function signedAcceptUrl(UserInvitation $invitation, string $code, string $route = 'invitation.accept'): string
    {
        return URL::temporarySignedRoute($route, $invitation->expires_at, ['code' => $code]);
    }

    /** Creates the invited account and signs nobody in; the caller does. */
    public function accept(UserInvitation $invitation, array $data): User
    {
        return DB::transaction(function () use ($invitation, $data) {
            if (User::where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['email' => 'An account already exists for this address. Sign in instead.']);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'fleet_provider_id' => $invitation->fleet_provider_id,
            ]);
            $user->syncRoles(Role::whereIn('id', $invitation->role_ids ?? [])->get());
            $user->events()->sync(Event::whereIn('id', $invitation->event_ids ?? [])->pluck('id'));

            $invitation->update(['accepted_at' => now(), 'accepted_user_id' => $user->id]);
            AuditLog::change('Invitation accepted', $user->email, $this->accessSummary($invitation->role_ids, $invitation->event_ids), $user);

            return $user;
        });
    }

    /**
     * SC/LOC staff: creates the account, or adds the roles and events to an existing one (never removes any).
     *
     * @return array{user: User, created: bool, reactivated: bool}
     */
    public function grant(array $data, User $actor): array
    {
        $email = Str::lower(trim($data['email']));

        $result = DB::transaction(function () use ($data, $email, $actor) {
            $user = User::where('email', $email)->first();
            $created = $user === null;
            $reactivated = false;

            if ($created) {
                // They sign in with Microsoft; the password is never told to anyone.
                $user = User::create(['name' => $data['name'], 'email' => $email, 'password' => Hash::make(Str::random(40))]);
            } elseif (! $user->is_active) {
                $this->setActive($user, true, $actor);
                $reactivated = true;
            }

            $user->assignRole(Role::whereIn('id', $data['role_ids'])->get());
            $user->events()->syncWithoutDetaching(Event::whereIn('id', $data['event_ids'] ?? [])->pluck('id'));

            AuditLog::change($created ? 'Access granted (new user)' : 'Access granted', $email, $this->accessSummary($data['role_ids'], $data['event_ids'] ?? []), $user);

            return ['user' => $user, 'created' => $created, 'reactivated' => $reactivated];
        });

        if ($data['notify'] ?? true) {
            Mail::to($result['user']->email)->send(new AccessGrantedMail(
                $result['user'],
                $this->names(Role::class, $data['role_ids']),
                $this->names(Event::class, $data['event_ids'] ?? []),
                $actor->name,
            ));
        }

        return $result;
    }

    /**
     * Roles that see every event don't need event assignments; everyone else does, or they see nothing.
     *
     * @param  array<int>  $roleIds
     */
    public static function needsEvents(array $roleIds): bool
    {
        return ! Role::with('permissions:id,name')->whereIn('id', $roleIds)->get()
            ->contains(fn (Role $role) => $role->name === 'admin' || $role->permissions->contains('name', 'events.access-all'));
    }

    /** Only the SecurityRole may hand out the SecurityRole. */
    public static function assertCanGrantRoles(User $actor, array $roleIds): void
    {
        $securityId = Role::where('name', User::SECURITY_ROLE)->value('id');
        abort_if(
            $securityId && in_array((int) $securityId, array_map('intval', $roleIds), true) && ! $actor->can(User::ACCESS_PERMISSION),
            403,
            'Only the SecurityRole can grant the SecurityRole.',
        );
    }

    private function sendInvitation(UserInvitation $invitation, string $code, User $actor): void
    {
        Mail::to($invitation->email)->send(new UserInvitationMail(
            $invitation,
            self::shortLink($code),
            $this->names(Role::class, $invitation->role_ids ?? []),
            $this->names(Event::class, $invitation->event_ids ?? []),
            $actor->name,
        ));
    }

    /** @return list<string> */
    private function names(string $model, array $ids): array
    {
        return $model::whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }

    private function accessSummary(?array $roleIds, ?array $eventIds): array
    {
        return [
            'roles' => $this->names(Role::class, $roleIds ?? []),
            'events' => Event::whereIn('id', $eventIds ?? [])->pluck('id')->sort()->values()->all(),
        ];
    }
}
