<?php

namespace Tests\Feature;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Every state-changing route must carry a permission/role guard. The few that rely on
 * authorization inside the controller are listed here, each covered by its own denial tests.
 */
class WriteRouteAuthorizationTest extends TestCase
{
    /** Public or authentication-only by nature. */
    private const AUTH_FLOW = [
        'login.attempt', 'logout', 'password.email', 'password.update',
        'api.mobile.login', 'api.mobile.logout',
        'session.active-event', 'session.active-plan',
    ];

    /** Authorized per job in the controller (JobOperationPolicy or ScopesMobileAccess). */
    private const CONTROLLER_AUTHORIZED = [
        'job.updateStatus', 'job.reinstate', 'job.issues.resolve',
        'checkpoint.complete', 'checkpoint.override',
        'api.mobile.checkpoints.complete', 'api.mobile.jobs.issues.store',
        'api.checkpoint.quick-complete',
    ];

    public function test_every_write_route_has_a_guard_or_is_listed(): void
    {
        $allowed = [...self::AUTH_FLOW, ...self::CONTROLLER_AUTHORIZED];
        $unguarded = [];

        /** @var LaravelRoute $route */
        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (! array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                continue;
            }

            $guarded = collect($route->gatherMiddleware())
                ->filter(fn ($m) => is_string($m))
                ->contains(fn ($m) => preg_match('/^(permission|role|role_or_permission|can):|PermissionMiddleware|RoleMiddleware/', $m));

            if (! $guarded && ! in_array($route->getName(), $allowed, true)) {
                $unguarded[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertSame([], $unguarded, 'These write routes are authenticated-only. Add permission/role middleware, or authorize in the controller and list them here with tests.');
    }
}
