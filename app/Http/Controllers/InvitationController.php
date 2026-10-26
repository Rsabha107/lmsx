<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\UserInvitation;
use App\Services\UserAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/** The invitee's side: the short link from the email, the signed accept page, and creating the account. */
class InvitationController extends Controller
{
    /** /i/{code}: resolves the short link to the signed, expiring accept page. */
    public function resolve(string $code): RedirectResponse|Response
    {
        $invitation = UserInvitation::findByCode($code);

        if (! $invitation?->isPending()) {
            return $this->unavailable($invitation);
        }

        return redirect()->to(UserAccessService::signedAcceptUrl($invitation, $code));
    }

    public function show(Request $request): Response
    {
        $code = (string) $request->query('code');
        $invitation = UserInvitation::findByCode($code);

        if (! $invitation?->isPending()) {
            return $this->unavailable($invitation);
        }

        return Inertia::render('Auth/AcceptInvitation', [
            'state' => 'open',
            'invitation' => [
                'email' => $invitation->email,
                'name' => $invitation->name,
                'roles' => Role::whereIn('id', $invitation->role_ids ?? [])->orderBy('name')->pluck('name'),
                'events' => Event::whereIn('id', $invitation->event_ids ?? [])->orderBy('name')->pluck('name'),
                'expires_at' => $invitation->expires_at->format('D, d M Y H:i'),
            ],
            'submitUrl' => UserAccessService::signedAcceptUrl($invitation, $code, 'invitation.store'),
        ]);
    }

    public function store(Request $request, UserAccessService $access): RedirectResponse|Response
    {
        $invitation = UserInvitation::findByCode((string) $request->query('code'));

        if (! $invitation?->isPending()) {
            return $this->unavailable($invitation);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:40',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $access->accept($invitation, $data);

        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->route('dashboard')->with('success', 'Welcome to NAQLA LMS. Your account is ready.');
    }

    private function unavailable(?UserInvitation $invitation): Response
    {
        return Inertia::render('Auth/AcceptInvitation', [
            'state' => $invitation?->status() ?? 'invalid',
        ]);
    }
}
