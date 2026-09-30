<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class MicrosoftController extends Controller
{
    public function redirectToMicrosoft(): SymfonyRedirectResponse
    {
        return Socialite::driver('microsoft')->redirect();
    }

    public function handleMicrosoftCallback(Request $request): RedirectResponse
    {
        try {
            $azureUser = Socialite::driver('microsoft')->user();
            $email = $azureUser->getEmail();
            $user = $email ? User::where('email', $email)->first() : null;

            if (! $user) {
                Log::warning('Microsoft login blocked — email not in system: '.$email);

                return $this->microsoftLogout($request, 'Your Microsoft account is not authorised to access this system. Please contact the administrator.');
            }

            $user->update([
                'name'        => $azureUser->getName() ?: $user->name,
                'provider'    => 'microsoft',
                'provider_id' => $azureUser->getId(),
            ]);

            Auth::guard('web')->logout();
            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('login_method', 'microsoft');

            Log::info('Microsoft login successful: '.$user->email);

            // Field supervisors have no desk console, so honouring an intended
            // console URL would land them on a 403.
            if (! $user->can('console.view')) {
                $request->session()->forget('url.intended');

                return redirect()->route('jobs.mobile');
            }

            return redirect()->intended('/');
        } catch (Throwable $e) {
            Log::error('Microsoft callback error: '.$e->getMessage());

            return $this->microsoftLogout($request, 'Unable to sign in with Microsoft. Please try again.');
        }
    }

    private function microsoftLogout(Request $request, string $errorMessage): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Flash into the fresh session so it survives the Microsoft round-trip
        $request->session()->flash('error', $errorMessage);

        /** @var \SocialiteProviders\Microsoft\Provider $microsoft */
        $microsoft = Socialite::driver('microsoft');

        return redirect($microsoft->getLogoutUrl(route('login')));
    }
}
