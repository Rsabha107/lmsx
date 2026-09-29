<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agency users may read the console but change only a movement's crew.
 *
 * Several write routes are guarded by a view permission alone (venues, the
 * daily email, job status), so the agency's writes are allow-listed instead.
 */
class RestrictAgencyToCrewAssignment
{
    private const ALLOWED_WRITES = [
        'movements.assign-crew',
        'logout',
        'session.active-event',
        'session.active-plan',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user
            && ! $request->isMethodSafe()
            && $user->hasRole('agency')
            && ! $user->hasRole('admin')
            && ! in_array($request->route()?->getName(), self::ALLOWED_WRITES, true)
        ) {
            abort(403, 'Agency accounts can only change a movement\'s vehicle, driver and supervisor.');
        }

        return $next($request);
    }
}
