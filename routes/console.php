<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run the AI agent autonomous pipeline every 4 hours
Schedule::command('agent:auto-run')->everyFourHours()->withoutOverlapping();
