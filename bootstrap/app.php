<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/mobile',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Azure App Service (and any load balancer/reverse proxy) terminates TLS
        // at the edge and forwards requests over plain HTTP with X-Forwarded-*
        // headers. Without this, Request::isSecure() is always false behind the
        // proxy, which breaks signed URL validation (portal links, RSVP links)
        // since the scheme used to validate the signature won't match the one
        // used to generate it.
        //
        // Trusting '*' is only safe while the origin is unreachable except through
        // the load balancer: anyone who can hit it directly can forge their client
        // IP, scheme and host. Set TRUSTED_PROXIES to the balancer's CIDR(s) if the
        // origin is ever exposed.
        $middleware->trustProxies(
            at: array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '*'))),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB
        );

        // Blocks Host-header poisoning (password-reset links pointing at an
        // attacker's domain). Opt-in, because a wrong value 403s every request.
        if ($trustedHosts = env('TRUSTED_HOSTS')) {
            $middleware->trustHosts(
                at: array_map('trim', explode(',', (string) $trustedHosts)),
                subdomains: true,
            );
        }
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\CheckInactivity::class,
        ]);

        // Spatie's route middleware aliases aren't auto-registered in
        // Laravel 13's bootstrap/app.php-based middleware config — needed
        // for `permission:` / `role:` gates on routes (e.g. routes/web/ai.php).
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
