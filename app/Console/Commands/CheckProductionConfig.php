<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/** Run before or after a deploy; exits non-zero when a production safety setting is wrong. */
class CheckProductionConfig extends Command
{
    protected $signature = 'app:check-production';

    protected $description = 'Verify debug, session, proxy, host and token settings are safe for production';

    public function handle(): int
    {
        $trustedProxies = (string) env('TRUSTED_PROXIES', '');

        $checks = [
            ['APP_ENV is production', app()->environment('production')],
            ['APP_DEBUG is off', ! config('app.debug')],
            ['APP_KEY is set', filled(config('app.key'))],
            ['APP_URL uses https', str_starts_with((string) config('app.url'), 'https://')],
            ['Session cookie is Secure (SESSION_SECURE_COOKIE=true)', config('session.secure') === true],
            ['Session cookie is HttpOnly', config('session.http_only') === true],
            ['Session SameSite is not "none"', strtolower((string) config('session.same_site')) !== 'none'],
            ['TRUSTED_PROXIES is not "*" (set the balancer CIDRs, or leave unset for private ranges)', trim($trustedProxies) !== '*'],
            ['TRUSTED_HOSTS is set (Host-header poisoning)', filled(env('TRUSTED_HOSTS'))],
            ['Mobile tokens expire (SANCTUM_TOKEN_EXPIRATION)', config('sanctum.expiration') !== null],
            ['Database is not SQLite', config('database.default') !== 'sqlite'],
        ];

        $failed = 0;

        foreach ($checks as [$label, $ok]) {
            $ok ? $this->line("  <info>PASS</info>  {$label}") : $this->line("  <error>FAIL</error>  {$label}");
            $failed += $ok ? 0 : 1;
        }

        if ($failed) {
            $this->newLine();
            $this->error("{$failed} production check(s) failed.");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('All production checks passed.');

        return self::SUCCESS;
    }
}
