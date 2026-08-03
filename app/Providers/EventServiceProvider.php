<?php

namespace App\Providers;

use App\Listeners\SendQueueFailureAlert;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobFailed;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        JobFailed::class => [
            SendQueueFailureAlert::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
