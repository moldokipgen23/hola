<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSchedule;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;

/**
 * Demo transport data so the transport seat-booking flow can be tried end to
 * end: verified vendors, vehicles with seat layouts, and departures on the
 * popular Lamka routes with vendor-set competitive prices.
 */
class DemoTransportSeeder extends Seeder
{
    public function run(): void
    {
        $taxiCategory = Category::firstOrCreate(
            ['slug' => 'taxi'],
            ['name' => 'Taxi & Transport', 'module_type' => 'booking'],
        );

        $owner = User::firstOrCreate(
            ['email' => 'transport-demo@demo.hola'],
            ['name' => 'Demo Transport Owner', 'password' => bcrypt('password'), 'role' => 'owner', 'email_verified_at' => now()],
        );

        $vendors = [
            [
                'name' => 'Lamka Express Travels',
                'slug' => 'lamka-express-travels',
                'vehicles' => [
                    ['name' => 'Volvo Intercity Bus', 'type' => 'bus', 'seats' => 32, 'price' => 450],
                    ['name' => 'Tempo Traveller', 'type' => 'tempo', 'seats' => 12, 'price' => 380],
                ],
                'rentals' => [
                    ['name' => 'Mahindra Bolero (Hire)', 'type' => 'bolero', 'seats' => 6, 'per_day' => 2500],
                    ['name' => 'Tata 407 Goods Truck', 'type' => 'truck', 'seats' => 2, 'per_day' => 3200],
                ],
            ],
            [
                'name' => 'Hillside Bus Service',
                'slug' => 'hillside-bus-service',
                'vehicles' => [
                    ['name' => 'Standard Bus', 'type' => 'bus', 'seats' => 40, 'price' => 400],
                    ['name' => 'Bolero SUV', 'type' => 'bolero', 'seats' => 6, 'price' => 500],
                ],
                'rentals' => [
                    ['name' => 'Maruti Ertiga (Hire)', 'type' => 'suv', 'seats' => 7, 'per_day' => 2800],
                    ['name' => 'Pickup (Goods)', 'type' => 'pickup', 'seats' => 2, 'per_day' => 1800],
                ],
            ],
        ];

        foreach ($vendors as $vendorData) {
            $business = Business::firstOrCreate(
                ['slug' => $vendorData['slug']],
                [
                    'name' => $vendorData['name'],
                    'category_id' => $taxiCategory->id,
                    'address' => 'Lamka Main Bazaar',
                    'phone' => '9000000005',
                    'whatsapp' => '9000000005',
                    'verification_status' => 'verified',
                    'is_active' => true,
                    'created_by' => $owner->id,
                    'enabled_modules' => ['transport' => true],
                ],
            );

            foreach ($vendorData['vehicles'] as $vehicleData) {
                $type = VehicleType::where('slug', $vehicleData['type'])->first();
                if (! $type) {
                    continue;
                }

                $vehicle = Vehicle::firstOrCreate(
                    ['business_id' => $business->id, 'name' => $vehicleData['name']],
                    [
                        'type' => $type->slug,
                        'service_mode' => 'bus',
                        'seats' => $vehicleData['seats'],
                        'base_fare' => $vehicleData['price'],
                        'fare_per_km' => 0,
                        'is_active' => true,
                        'availability_status' => 'available',
                    ],
                );

                $this->createSchedules($business, $vehicle, $vehicleData['price']);
            }

            $this->createRentals($business, $vendorData['rentals'] ?? []);
        }

        $this->command?->info('Demo transport vendors + schedules + rentals seeded.');
    }

    /**
     * Rental inventory (service_mode "rental" with a daily price) so the
     * vehicle hire flow has vehicles to pick. These get no seat schedules.
     */
    private function createRentals(Business $business, array $rentals): void
    {
        foreach ($rentals as $rentalData) {
            $type = VehicleType::where('slug', $rentalData['type'])->first();
            if (! $type) {
                continue;
            }

            Vehicle::firstOrCreate(
                ['business_id' => $business->id, 'name' => $rentalData['name']],
                [
                    'type' => $type->slug,
                    'service_mode' => 'rental',
                    'seats' => $rentalData['seats'],
                    'price_per_day' => $rentalData['per_day'],
                    'base_fare' => 0,
                    'fare_per_km' => 0,
                    'is_active' => true,
                    'availability_status' => 'available',
                ],
            );
        }
    }

    private function createSchedules(Business $business, Vehicle $vehicle, float $price): void
    {
        $routes = TransportRouteSeeder::ROUTES;

        // Only seed a handful of departures so it is realistic but not noisy.
        foreach ([$routes[0], $routes[1], $routes[4]] as [$origin, $destination, $km, $hours, $_]) {
            foreach (['07:00', '15:00'] as $time) {
                $date = now()->addDays(random_int(1, 7))->toDateString();

                VehicleSchedule::firstOrCreate(
                    [
                        'business_id' => $business->id,
                        'origin' => $origin,
                        'destination' => $destination,
                        'departure_date' => $date,
                        'departure_time' => $time,
                    ],
                    [
                        'vehicle_id' => $vehicle->id,
                        'distance_km' => $km,
                        'estimated_minutes' => (int) round($hours * 60),
                        'seats_capacity' => $vehicle->seats,
                        'price' => $price,
                        'status' => 'scheduled',
                        'boarding_stops' => [
                            ['name' => "{$origin} Main Stand", 'time' => $time, 'price_offset' => 0],
                            ['name' => "{$origin} Bazaar", 'time' => substr($time, 0, 2).':20', 'price_offset' => 15],
                        ],
                        'drop_stops' => [
                            ['name' => "{$destination} Central", 'time' => null, 'price_offset' => 0],
                        ],
                    ],
                );
            }
        }
    }
}
