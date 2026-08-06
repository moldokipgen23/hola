<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\TransportRoute;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use Database\Seeders\DemoTransportSeeder;
use Database\Seeders\TransportRouteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_seed_wires_transport_routes_and_demo_inventory(): void
    {
        $this->seed();

        // Master routes seeded both ways.
        $this->assertSame(6, TransportRoute::count());

        // Demo transport vendors exist with the transport module enabled.
        $express = Business::where('slug', 'lamka-express-travels')->first();
        $hillside = Business::where('slug', 'hillside-bus-service')->first();

        $this->assertNotNull($express);
        $this->assertNotNull($hillside);
        $this->assertTrue($express->hasModule('transport'));
        $this->assertTrue($hillside->hasModule('transport'));

        // Seat-booking schedules for the shared bus flow.
        $this->assertTrue(VehicleSchedule::where('business_id', $express->id)->exists());

        // Rental inventory (service_mode rental + price_per_day).
        $rentals = Vehicle::where('service_mode', 'rental')
            ->whereNotNull('price_per_day')
            ->whereIn('business_id', [$express->id, $hillside->id])
            ->get();

        $this->assertTrue($rentals->count() >= 4);
        $this->assertSame('rental', $rentals->first()->service_mode);
        $this->assertTrue((float) $rentals->first()->price_per_day > 0);
    }

    public function test_transport_seeders_are_idempotent(): void
    {
        $this->seed(TransportRouteSeeder::class);
        $this->seed(DemoTransportSeeder::class);
        $this->seed(TransportRouteSeeder::class);
        $this->seed(DemoTransportSeeder::class);

        $this->assertSame(6, TransportRoute::count());
        $this->assertSame(2, Business::whereIn('slug', ['lamka-express-travels', 'hillside-bus-service'])->count());
    }
}
