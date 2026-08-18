<?php

namespace App\Providers;

use App\Models\JobOperation;
use App\Models\Movement;
use App\Policies\JobOperationPolicy;
use App\Policies\MovementPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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

        Gate::policy(Movement::class, MovementPolicy::class);
        Gate::policy(JobOperation::class, JobOperationPolicy::class);

        // Conservative limit — this hits a paid external API synchronously
        // on every request, so it needs its own (tighter) limiter rather
        // than the framework default.
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
