<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser hardening headers on every response. The CSP only limits framing, plugins and <base>,
 * which needs no script/style allow-list (Vite, Inertia and the fonts keep working); a full script
 * CSP belongs at the edge once it has been tried in report-only mode.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        if (! $headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', "frame-ancestors 'self'; object-src 'none'; base-uri 'self'");
        }

        // Only over HTTPS, so a local http:// install is never pinned.
        if ($request->isSecure() && app()->environment('production', 'azure')) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
