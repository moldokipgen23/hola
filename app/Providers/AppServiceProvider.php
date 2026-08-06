<?php

namespace App\Providers;

use App\Models\Subcategory;
use App\Observers\SubcategoryObserver;
use App\Services\AdminNavService;
use App\Services\LaunchControlService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
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
        Subcategory::observe(SubcategoryObserver::class);

        Paginator::defaultView('partials._pagination');

        View::composer(['layouts.public', 'public.*', 'partials._business-card'], function ($view) {
            $view->with('launchControl', app(LaunchControlService::class));
        });

        View::composer('vendor.layouts.dashboard', function ($view) {
            $view->with('launchControl', app(LaunchControlService::class));
        });

        View::composer('layouts.admin', function ($view) {
            $nav = app(AdminNavService::class);
            $view->with('adminBadges', $nav->badges());
            $view->with('isPowerUser', $nav->isPowerUser());
            $view->with('menuGroups', $nav->menuItems());
            $view->with('launchControl', app(LaunchControlService::class));
        });
    }
}
