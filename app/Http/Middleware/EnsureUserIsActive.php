<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A deactivated user's open browser session or mobile token stops working on its next request. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->is_active) {
            if ($request->bearerToken()) {
                $user->currentAccessToken()?->delete();

                return response()->json(['message' => User::INACTIVE_MESSAGE], 401);
            }

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => User::INACTIVE_MESSAGE], 401);
            }

            return redirect()->route('login')->with('error', User::INACTIVE_MESSAGE);
        }

        return $next($request);
    }
}
