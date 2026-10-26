<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Reject event-owned requests outside the authenticated user's assignments. */
class EnsureEventAccess
{
    public function handle(Request $request, Closure $next, string $parameter = 'id'): Response
    {
        abort_unless($request->user()?->canAccessEvent((int) $request->route($parameter)), 403);

        return $next($request);
    }
}
