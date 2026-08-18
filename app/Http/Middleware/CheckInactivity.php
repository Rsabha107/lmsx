<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckInactivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            // Get inactivity timeout in minutes (default: 30 minutes)
            $inactivityTimeout = config('session.inactivity_timeout', 30);

            // Get last activity timestamp from session
            $lastActivity = session('last_activity_time');

            if ($lastActivity) {
                // Calculate minutes since last activity
                $inactiveMinutes = (time() - $lastActivity) / 60;

                // If user has been inactive for longer than timeout, log them out
                if ($inactiveMinutes > $inactivityTimeout) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()->route('login')
                        ->with('status', 'Your session expired due to inactivity.');
                }
            }

            // Update last activity timestamp
            session(['last_activity_time' => time()]);
        }

        return $next($request);
    }
}
