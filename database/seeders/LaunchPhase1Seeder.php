<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use App\Services\LaunchControlService;
use Illuminate\Database\Seeder;

/**
 * Phase-1 launch configuration: the public surface is Directory + Turf only.
 *
 * ON  — world.discover, experience.directory, world.book,
 *       module.bookings, module.turf, experience.turf
 * OFF — everything else, including world.shop, world.ride and payments.online.
 *
 * Run after migrations (which sync definitions at their defaults):
 *   php artisan db:seed --class=LaunchPhase1Seeder
 */
class LaunchPhase1Seeder extends Seeder
{
    public const ENABLED_KEYS = [
        'world.book',
        'world.discover',
        'module.bookings',
        'module.turf',
        'experience.directory',
        'experience.turf',
    ];

    public function run(): void
    {
        foreach (array_keys(LaunchControlService::DEFINITIONS) as $key) {
            FeatureFlag::updateOrCreate(
                ['key' => $key],
                [
                    'name' => LaunchControlService::DEFINITIONS[$key]['name'],
                    'description' => LaunchControlService::DEFINITIONS[$key]['description'],
                    'group' => LaunchControlService::DEFINITIONS[$key]['group'],
                    'is_enabled' => in_array($key, self::ENABLED_KEYS, true),
                    'is_visible_to_customers' => true,
                    'metadata' => ['system' => true],
                ],
            );
        }

        app(LaunchControlService::class)->clearCache();
    }
}
