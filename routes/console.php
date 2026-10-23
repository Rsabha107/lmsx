<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('flights:sync')->everyThirtyMinutes();
Schedule::command('push:job-reminders')->everyMinute()->withoutOverlapping();
// Empty the recycle bin of what is past its 30 days, at night Doha time.
Schedule::command('deletions:purge --apply')->dailyAt('03:30')->withoutOverlapping();
