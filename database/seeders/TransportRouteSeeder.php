<?php

namespace Database\Seeders;

use App\Models\TransportRoute;
use Illuminate\Database\Seeder;

/**
 * Popular intercity transport routes around Lamka (Churachandpur), seeded in
 * both directions so vendors can pick them and customers can search either way.
 *
 * Distance/time are representative baseline values only — vendors set their own
 * competitive price and travel time per departure on each schedule.
 */
class TransportRouteSeeder extends Seeder
{
    public const ROUTES = [
        ['Lamka', 'Aizawl', 200, 5, 300],
        ['Aizawl', 'Lamka', 200, 5, 300],
        ['Lamka', 'Kanggui', 100, 3, 180],
        ['Kanggui', 'Lamka', 100, 3, 180],
        ['Lamka', 'Moreh', 65, 2, 150],
        ['Moreh', 'Lamka', 65, 2, 150],
    ];

    public function run(): void
    {
        foreach (self::ROUTES as $order => [$origin, $destination, $km, $hours, $fare]) {
            TransportRoute::updateOrCreate(
                ['origin' => $origin, 'destination' => $destination],
                [
                    'distance_km' => $km,
                    'base_fare' => $fare,
                    'estimated_minutes' => (int) round($hours * 60),
                    'is_active' => true,
                    'sort_order' => $order,
                ]
            );
        }
    }
}
