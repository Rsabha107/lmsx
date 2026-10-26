<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserInvitation;
use App\Services\UserAccessService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Setups → Users: switching users on/off, invitations and SC/LOC access. Admin only (routes). */
class UserAccessController extends Controller
{
    public function __construct(private UserAccessService $access)
    {
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $active = $request->validate(['active' => 'required|boolean'])['active'];

        abort_if($user->id === $request->user()->id && ! $active, 403, 'You cannot deactivate your own account.');
        abort_if(
            $user->hasRole(User::SECURITY_ROLE) && ! $request->user()->can(User::ACCESS_PERMISSION),
            403,
            'Only the SecurityRole can change a SecurityRole holder.',
        );

        $this->access->setActive($user, (bool) $active, $request->user());

        return back()->with('success', $active ? "{$user->name} can sign in again." : "{$user->name} is deactivated and signed out everywhere.");
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email'), function (string $a, mixed $v, Closure $fail) {
                if (User::isStaffEmail($v)) {
                    $fail('SC/LOC staff sign in with Microsoft. Use Grant SC/LOC access instead.');
                }
            }],
            'name' => 'nullable|string|max:255',
            'fleet_provider_id' => 'nullable|integer|exists:fleet_providers,id',
            'expires_in_days' => 'nullable|integer|min:1|max:30',
            ...$this->accessRules(),
        ], ['email.unique' => 'A user with this email already exists.']);

        $this->checkAccess($request, $data);
        $invitation = $this->access->invite($data, $request->user());

        return back()->with('success', "Invitation sent to {$invitation->email}. The link expires on {$invitation->expires_at->format('d M Y')}.");
    }

    public function resend(Request $request, UserInvitation $invitation): RedirectResponse
    {
        abort_if($invitation->accepted_at !== null, 422, 'This invitation was already used.');
        abort_if(User::where('email', $invitation->email)->exists(), 422, 'An account already exists for this address.');

        $this->access->resend($invitation, $request->user());

        return back()->with('success', "Invitation sent again to {$invitation->email}. The earlier link no longer works.");
    }

    public function revoke(UserInvitation $invitation): RedirectResponse
    {
        abort_if($invitation->accepted_at !== null, 422, 'This invitation was already used.');
        $this->access->revoke($invitation);

        return back()->with('success', "Invitation for {$invitation->email} cancelled.");
    }

    public function grant(Request $request): RedirectResponse
    {
        $exists = User::where('email', $request->input('email'))->exists();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', function (string $a, mixed $v, Closure $fail) {
                if (! User::isStaffEmail($v)) {
                    $fail('Only '.collect(config('services.microsoft.staff_domains'))->map(fn ($d) => "@{$d}")->join(' or ').' addresses. Invite anyone else.');
                }
            }],
            'name' => [$exists ? 'nullable' : 'required', 'string', 'max:255'],
            'notify' => 'boolean',
            ...$this->accessRules(),
        ]);

        // An existing account that already has events needs no new one.
        $this->checkAccess($request, $data, $exists && (bool) User::where('email', $data['email'])->first()?->events()->exists());
        ['user' => $user, 'created' => $created, 'reactivated' => $reactivated] = $this->access->grant($data, $request->user());

        $message = $created ? "Access granted: {$user->name} can now sign in with their SC/LOC account." : "Access added to {$user->name}'s existing account.";

        return back()->with('success', $message.($reactivated ? ' The account was inactive and is active again.' : '').(($data['notify'] ?? true) ? ' They have been emailed.' : ''));
    }

    private function accessRules(): array
    {
        return [
            'role_ids' => 'required|array|min:1',
            'role_ids.*' => 'integer|exists:roles,id',
            'event_ids' => 'nullable|array',
            'event_ids.*' => 'integer|exists:events,id',
        ];
    }

    private function checkAccess(Request $request, array $data, bool $hasEvents = false): void
    {
        UserAccessService::assertCanGrantRoles($request->user(), $data['role_ids']);

        if (empty($data['event_ids']) && ! $hasEvents && UserAccessService::needsEvents($data['role_ids'])) {
            throw ValidationException::withMessages(['event_ids' => 'Pick at least one event; without one this user would see nothing.']);
        }
    }
}
