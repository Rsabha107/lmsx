<?php

namespace App\Providers;

use App\Models\JobOperation;
use App\Models\Movement;
use App\Policies\JobOperationPolicy;
use App\Policies\MovementPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
        if ($this->app->environment('azure')) {
            URL::forceScheme('https');
        }

        Event::listen(SocialiteWasCalled::class, function (SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', \SocialiteProviders\Microsoft\Provider::class);
        });

        Gate::policy(Movement::class, MovementPolicy::class);
        Gate::policy(JobOperation::class, JobOperationPolicy::class);

        // Conservative limit — this hits a paid external API synchronously
        // on every request, so it needs its own (tighter) limiter rather
        // than the framework default.
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        // Per signed-in user, so one supervisor's retry storm can't starve the others behind a shared NAT.
        RateLimiter::for('mobile', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        // Photo/signature uploads cost storage and CPU.
        RateLimiter::for('mobile-upload', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
    }
}
