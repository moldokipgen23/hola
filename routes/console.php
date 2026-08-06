<?php

use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run the AI agent autonomous pipeline every 4 hours
Schedule::command('agent:auto-run')->everyFourHours()->withoutOverlapping();

// Sync businesses with Google daily (detect changes, closures, new photos)
Schedule::command('google:sync --limit=50')->dailyAt('03:00')->withoutOverlapping();

// Download external photos for imported businesses (server-side, no key exposure)
Schedule::command('photos:download --limit=30')->everySixHours()->withoutOverlapping();

// Deep change detection weekly (downloads photos, logs changes to JSON)
Schedule::command('app:detect-business-changes --limit=500')->weeklyOn(0, '02:00')->withoutOverlapping();

// Auto-pilot claim notifications: invite + remind unclaimed businesses.
// Runs daily at 10am but only SENDS when `autopilot_claim_enabled` is ON.
Schedule::command('autopilot:claim-notifications')
    ->dailyAt('10:00')
    ->withoutOverlapping();

// Operational heartbeat and stale agent-task recovery.
Schedule::call(function () {
    Cache::put('health:scheduler:last_run_at', now()->toIso8601String(), now()->addMinutes(10));
})->everyMinute()->name('scheduler-heartbeat');

Schedule::job(new RecordQueueHeartbeat)->everyMinute()->name('queue-heartbeat');

Schedule::command('agent:maintain-tasks --stale-minutes=60')
    ->hourly()
    ->withoutOverlapping();
