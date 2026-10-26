<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AuthController extends Controller
{
    public function showLogin(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            // Told only after the right password, so it reveals nothing to a guesser.
            if (! $request->user()->is_active) {
                Auth::logout();

                return back()->withErrors(['email' => User::INACTIVE_MESSAGE])->onlyInput('email');
            }

            $request->session()->regenerate();
            $request->user()->forceFill(['last_login_at' => now()])->saveQuietly();

            // Field supervisors have no desk console, so honouring an intended
            // console URL would land them on a 403.
            if (! $request->user()->can('console.view')) {
                $request->session()->forget('url.intended');

                return redirect()->route('dashboard');
            }

            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => 'These credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse|HttpResponse
    {
        $viaMicrosoft = $request->session()->get('login_method') === 'microsoft';

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($viaMicrosoft) {
            /** @var \SocialiteProviders\Microsoft\Provider $microsoft */
            $microsoft = Socialite::driver('microsoft');

            return Inertia::location($microsoft->getLogoutUrl(route('login')));
        }

        return redirect()->route('login');
    }

    public function showForgotPassword(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // One reply whatever happened (sent, unknown address, throttled), so the form never reveals who has an account.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If that address has an account, a reset link is on its way.');
    }

    public function showResetPassword(string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token'                 => 'required',
            'email'                 => 'required|email',
            'password'              => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                // A password they chose themselves replaces any temporary one.
                $user->forceFill(['password' => Hash::make($password), 'must_change_password' => false])
                     ->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
