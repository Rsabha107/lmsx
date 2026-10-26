<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A user signed in with an admin-set temporary password reaches nothing but the change-password
 * page (and sign-out) until they choose their own. Microsoft sign-ins never used that password.
 */
class EnsurePasswordChanged
{
    private const ALLOWED = ['password.change', 'password.change.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password
            && $request->session()->get('login_method') !== 'microsoft'
            && ! in_array($request->route()?->getName(), self::ALLOWED, true)) {
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => 'Choose a new password first.'], 409);
            }

            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
