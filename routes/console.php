<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run the AI agent autonomous pipeline every 4 hours
Schedule::command('agent:auto-run')->everyFourHours()->withoutOverlapping();

// Sync businesses with Google daily (detect changes, closures, new photos)
Schedule::command('google:sync --limit=50')->dailyAt('03:00')->withoutOverlapping();

// Deep change detection weekly (downloads photos, logs changes to JSON)
Schedule::command('app:detect-business-changes --limit=500')->weeklyOn(0, '02:00')->withoutOverlapping();

// Notify unclaimed businesses daily at 10am
Schedule::command('business:notify-unclaimed --days=3 --limit=20')->dailyAt('10:00')->withoutOverlapping();
