<?php

namespace App\Providers;

use App\Models\Business;
use App\Models\MediaLibrary;
use App\Policies\BusinessPolicy;
use App\Policies\MediaPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected array $policies = [
        Business::class => BusinessPolicy::class,
        MediaLibrary::class => MediaPolicy::class,
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
