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
        // Trusting '*' lets anyone who can reach the origin directly forge their client IP,
        // scheme and host, so the default is private/loopback ranges only: a TLS-terminating
        // balancer (Azure App Service) reaches the app from a private address, while a direct
        // public request is not trusted. Set TRUSTED_PROXIES to the balancer's CIDR(s) to be
        // exact, or to '*' only if the origin is unreachable except through it.
        //
        // Passed through as a raw string: TrustProxies only recognises the wildcard
        // as the literal string '*', and splits comma-separated lists itself. Handing
        // it ['*'] instead makes it match '*' as an IP, which never matches - the
        // proxy goes untrusted and every generated URL falls back to http://.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,169.254.0.0/16,fc00::/7'),
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
            \App\Http\Middleware\RestrictAgencyToCrewAssignment::class,
        ]);

        // Spatie's route middleware aliases aren't auto-registered in
        // Laravel 13's bootstrap/app.php-based middleware config — needed
        // for `permission:` / `role:` gates on routes (e.g. routes/web/ai.php).
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'ui_flag' => \App\Http\Middleware\EnsureUiFlagEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
