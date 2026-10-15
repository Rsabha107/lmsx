<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class MicrosoftController extends Controller
{
    /** The mobile app's sign-in callback (custom URL scheme). */
    public const MOBILE_CALLBACK = 'naqlalms://sso';

    public function redirectToMicrosoft(): SymfonyRedirectResponse
    {
        return Socialite::driver('microsoft')->redirect();
    }

    /**
     * Starts SSO for the mobile app. The callback hands the app a one-time code, redeemable only
     * with the verifier behind this challenge (PKCE), so a code intercepted on the way is useless.
     */
    public function redirectFromMobile(Request $request): SymfonyRedirectResponse
    {
        $validator = Validator::make($request->query(), [
            'challenge' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{43}$/'],
            'state' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{16,128}$/'],
        ]);
        abort_if($validator->fails(), 400, 'Invalid sign-in request.');

        $request->session()->put('mobile_sso', $validator->validated());

        return Socialite::driver('microsoft')->redirect();
    }

    public function handleMicrosoftCallback(Request $request): SymfonyRedirectResponse
    {
        $mobile = $request->session()->pull('mobile_sso');

        try {
            $azureUser = Socialite::driver('microsoft')->user();
            $email = $azureUser->getEmail();
            $user = $email ? User::where('email', $email)->first() : null;

            if (! $user) {
                Log::warning('Microsoft login blocked — email not in system: '.$email);
                $message = 'Your Microsoft account is not authorised to access this system. Please contact the administrator.';

                return $mobile ? $this->toMobile($mobile, ['error' => $message]) : $this->microsoftLogout($request, $message);
            }

            $user->update([
                'name'        => $azureUser->getName() ?: $user->name,
                'provider'    => 'microsoft',
                'provider_id' => $azureUser->getId(),
            ]);

            if ($mobile) {
                $code = Str::random(64);
                Cache::put(self::mobileCodeKey($code), ['user_id' => $user->id, 'challenge' => $mobile['challenge']], now()->addMinutes(2));
                Log::info('Microsoft mobile login: '.$user->email);

                return $this->toMobile($mobile, ['code' => $code]);
            }

            Auth::guard('web')->logout();
            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('login_method', 'microsoft');

            Log::info('Microsoft login successful: '.$user->email);

            // Field supervisors have no desk console, so honouring an intended
            // console URL would land them on a 403.
            if (! $user->can('console.view')) {
                $request->session()->forget('url.intended');

                return redirect()->route('dashboard');
            }

            return redirect()->intended('/');
        } catch (Throwable $e) {
            Log::error('Microsoft callback error: '.$e->getMessage());
            $message = 'Unable to sign in with Microsoft. Please try again.';

            return $mobile ? $this->toMobile($mobile, ['error' => $message]) : $this->microsoftLogout($request, $message);
        }
    }

    public static function mobileCodeKey(string $code): string
    {
        return 'mobile-sso:'.hash('sha256', $code);
    }

    /** @param array{state: string} $mobile */
    private function toMobile(array $mobile, array $params): RedirectResponse
    {
        return redirect()->away(self::MOBILE_CALLBACK.'?'.http_build_query($params + ['state' => $mobile['state']]));
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
