<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run by startup.sh (schedule:work). onOneServer: once even with several App Service instances (shared database cache).
Schedule::command('flights:sync')->everyThirtyMinutes()->onOneServer();
Schedule::command('push:job-reminders')->everyMinute()->withoutOverlapping()->onOneServer();
// Empty the recycle bin of what is past its 30 days, at night Doha time.
Schedule::command('deletions:purge --apply')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
