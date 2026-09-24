<?php

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes a route whose feature has been switched off in Setups > Settings.
 *
 * Used alongside the sidebar's own flag check: hiding a link only stops people
 * finding it, this stops them reaching it by URL.
 */
class EnsureUiFlagEnabled
{
    public function __construct(private SettingsService $settings)
    {
    }

    public function handle(Request $request, Closure $next, string $flag, string $label = 'This feature'): Response
    {
        abort_unless(
            $this->settings->getGlobalFlag($flag),
            403,
            "{$label} has been turned off by an administrator.",
        );

        return $next($request);
    }
}
