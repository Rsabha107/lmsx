<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agency users may read the console but change only a movement's crew and the vehicles, drivers and providers.
 *
 * Several write routes are guarded by a view permission alone (venues, the
 * daily email, job status), so the agency's writes are allow-listed instead.
 */
class RestrictAgencyToCrewAssignment
{
    private const ALLOWED_WRITES = [
        'movements.assign-crew',
        'fleet.vehicles.store',
        'fleet.vehicles.update',
        'fleet.vehicles.destroy',
        'fleet.drivers.store',
        'fleet.drivers.update',
        'fleet.drivers.destroy',
        'fleet.providers.store',
        'fleet.providers.update',
        'fleet.providers.destroy',
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
            $message = 'Agency accounts can only manage vehicles, drivers and providers, and assign a movement\'s vehicle, driver and supervisor.';

            // AccessRestrictedModal picks this up instead of Inertia's raw error page.
            if ($request->header('X-Inertia')) {
                return response()->json(['message' => $message], 403, ['X-Access-Restricted' => '1']);
            }

            abort(403, $message);
        }

        return $next($request);
    }
}
