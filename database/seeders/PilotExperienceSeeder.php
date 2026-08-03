<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Service;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class PilotExperienceSeeder extends Seeder
{
    public function run(): void
    {
        // STAY - 3 pilots (ready, request, contact)
        $stayBusiness = Business::where('primary_experience', 'stay')->first();
        if ($stayBusiness) {
            $this->createStayServices($stayBusiness);
        }

        // TURF - 3 pilots
        $turfBusiness = Business::where('primary_experience', 'turf')->first();
        if (!$turfBusiness) {
            $turfBusiness = Business::where('name', 'Test Football Turf')->first();
            if ($turfBusiness) {
                $turfBusiness->primary_experience = 'turf';
                $turfBusiness->enabled_experiences = ['directory', 'turf'];
                $turfBusiness->experience_config = ['turf' => ['availability_mode' => 'request']];
                $turfBusiness->enabled_modules = array_merge($turfBusiness->enabled_modules ?? [], ['turf' => true, 'bookings' => true]);
                $turfBusiness->save();
            }
        }
        if ($turfBusiness) {
            $this->createTurfServices($turfBusiness);
        }

        // TAXI - create pilot
        $taxiBusiness = Business::where('primary_experience', 'taxi')->first();
        if (!$taxiBusiness) {
            $taxiBusiness = Business::create([
                'category_id' => 1,
                'area_id' => 1,
                'name' => 'Pilot Taxi Service',
                'slug' => 'pilot-taxi-service',
                'description' => 'Pilot taxi operator for testing',
                'address' => 'Lamka Bazar',
                'locality' => 'Lamka',
                'district' => 'Churachandpur',
                'pincode' => '795114',
                'state' => 'Manipur',
                'latitude' => 24.335,
                'longitude' => 93.705,
                'phone' => '9000000010',
                'is_active' => true,
                'enabled_modules' => ['catalog' => false, 'orders' => false, 'bookings' => false, 'inventory' => false, 'transport' => true, 'turf' => false],
                'primary_experience' => 'taxi',
                'enabled_experiences' => ['directory', 'taxi'],
                'experience_config' => ['taxi' => ['availability_mode' => 'live']],
            ]);
        }
        $this->createTaxiVehicles($taxiBusiness);

        // SHARED TRANSPORT - create pilot
        $sharedBusiness = Business::where('primary_experience', 'shared_transport')->first();
        if (!$sharedBusiness) {
            $sharedBusiness = Business::create([
                'category_id' => 1,
                'area_id' => 1,
                'name' => 'Pilot Bus Service',
                'slug' => 'pilot-bus-service',
                'description' => 'Pilot shared transport operator',
                'address' => 'Lamka Bazar',
                'locality' => 'Lamka',
                'district' => 'Churachandpur',
                'pincode' => '795114',
                'state' => 'Manipur',
                'latitude' => 24.335,
                'longitude' => 93.705,
                'phone' => '9000000011',
                'is_active' => true,
                'enabled_modules' => ['catalog' => false, 'orders' => false, 'bookings' => false, 'inventory' => false, 'transport' => true, 'turf' => false],
                'primary_experience' => 'shared_transport',
                'enabled_experiences' => ['directory', 'shared_transport'],
                'experience_config' => ['shared_transport' => ['availability_mode' => 'request']],
            ]);
        }
        $this->createSharedVehicles($sharedBusiness);

        // VEHICLE RENTAL - create pilot
        $rentalBusiness = Business::where('primary_experience', 'vehicle_rental')->first();
        if (!$rentalBusiness) {
            $rentalBusiness = Business::create([
                'category_id' => 1,
                'area_id' => 1,
                'name' => 'Pilot Car Rental',
                'slug' => 'pilot-car-rental',
                'description' => 'Pilot vehicle rental operator',
                'address' => 'Lamka Bazar',
                'locality' => 'Lamka',
                'district' => 'Churachandpur',
                'pincode' => '795114',
                'state' => 'Manipur',
                'latitude' => 24.335,
                'longitude' => 93.705,
                'phone' => '9000000012',
                'is_active' => true,
                'enabled_modules' => ['catalog' => false, 'orders' => false, 'bookings' => false, 'inventory' => false, 'transport' => true, 'turf' => false],
                'primary_experience' => 'vehicle_rental',
                'enabled_experiences' => ['directory', 'vehicle_rental'],
                'experience_config' => ['vehicle_rental' => ['availability_mode' => 'request']],
            ]);
        }
        $this->createRentalVehicles($rentalBusiness);

        // GOODS TRANSPORT - create pilot
        $goodsBusiness = Business::where('primary_experience', 'goods_transport')->first();
        if (!$goodsBusiness) {
            $goodsBusiness = Business::create([
                'category_id' => 1,
                'area_id' => 1,
                'name' => 'Pilot Goods Transport',
                'slug' => 'pilot-goods-transport',
                'description' => 'Pilot goods transport operator',
                'address' => 'Lamka Bazar',
                'locality' => 'Lamka',
                'district' => 'Churachandpur',
                'pincode' => '795114',
                'state' => 'Manipur',
                'latitude' => 24.335,
                'longitude' => 93.705,
                'phone' => '9000000013',
                'is_active' => true,
                'enabled_modules' => ['catalog' => false, 'orders' => false, 'bookings' => false, 'inventory' => false, 'transport' => true, 'turf' => false],
                'primary_experience' => 'goods_transport',
                'enabled_experiences' => ['directory', 'goods_transport'],
                'experience_config' => ['goods_transport' => ['availability_mode' => 'request']],
            ]);
        }
        $this->createGoodsVehicles($goodsBusiness);

        // SEAT EVENT - create pilot
        $seatBusiness = Business::where('primary_experience', 'seat_event')->first();
        if (!$seatBusiness) {
            $seatBusiness = Business::create([
                'category_id' => 1,
                'area_id' => 1,
                'name' => 'Pilot Event Hall',
                'slug' => 'pilot-event-hall',
                'description' => 'Pilot seat event venue',
                'address' => 'Lamka Bazar',
                'locality' => 'Lamka',
                'district' => 'Churachandpur',
                'pincode' => '795114',
                'state' => 'Manipur',
                'latitude' => 24.335,
                'longitude' => 93.705,
                'phone' => '9000000014',
                'is_active' => true,
                'enabled_modules' => ['catalog' => false, 'orders' => false, 'bookings' => true, 'inventory' => false, 'transport' => false, 'turf' => false],
                'primary_experience' => 'seat_event',
                'enabled_experiences' => ['directory', 'seat_event'],
                'experience_config' => ['seat_event' => ['availability_mode' => 'request']],
            ]);
        }
        $this->createSeatServices($seatBusiness);

        $this->command->info('Pilot experience data seeded successfully.');
    }

    private function createStayServices(Business $business): void
    {
        Service::create([
            'business_id' => $business->id,
            'name' => 'Standard Room',
            'description' => 'Comfortable standard room with AC',
            'price' => 2500,
            'duration' => 1440,
            'is_active' => true,
            'sort_order' => 1,
            'booking_mode' => 'stay',
            'capacity' => 2,
            'inventory_units' => 5,
            'unit_label' => 'room',
            'price_unit' => 'night',
            'check_in_time' => '14:00',
            'check_out_time' => '11:00',
            'min_stay_nights' => 1,
            'max_stay_nights' => 30,
        ]);

        Service::create([
            'business_id' => $business->id,
            'name' => 'Deluxe Room',
            'description' => 'Spacious deluxe room with balcony',
            'price' => 4500,
            'duration' => 1440,
            'is_active' => true,
            'sort_order' => 2,
            'booking_mode' => 'stay',
            'capacity' => 3,
            'inventory_units' => 3,
            'unit_label' => 'room',
            'price_unit' => 'night',
            'check_in_time' => '14:00',
            'check_out_time' => '11:00',
            'min_stay_nights' => 1,
            'max_stay_nights' => 30,
        ]);

        Service::create([
            'business_id' => $business->id,
            'name' => 'Suite',
            'description' => 'Luxury suite with living area',
            'price' => 8000,
            'duration' => 1440,
            'is_active' => true,
            'sort_order' => 3,
            'booking_mode' => 'stay',
            'capacity' => 4,
            'inventory_units' => 2,
            'unit_label' => 'room',
            'price_unit' => 'night',
            'check_in_time' => '14:00',
            'check_out_time' => '11:00',
            'min_stay_nights' => 2,
            'max_stay_nights' => 14,
        ]);
    }

    private function createTurfServices(Business $business): void
    {
        Service::create([
            'business_id' => $business->id,
            'name' => 'Football Turf - 7v7',
            'description' => 'Artificial grass football turf with floodlights',
            'price' => 1200,
            'duration' => 60,
            'is_active' => true,
            'sort_order' => 1,
            'booking_mode' => 'slot',
            'capacity' => 14,
            'inventory_units' => 2,
            'unit_label' => 'court',
            'price_unit' => 'hour',
            'has_fixed_slots' => true,
        ]);

        Service::create([
            'business_id' => $business->id,
            'name' => 'Football Turf - 5v5',
            'description' => 'Small-sided football turf',
            'price' => 800,
            'duration' => 60,
            'is_active' => true,
            'sort_order' => 2,
            'booking_mode' => 'slot',
            'capacity' => 10,
            'inventory_units' => 1,
            'unit_label' => 'court',
            'price_unit' => 'hour',
            'has_fixed_slots' => true,
        ]);

        Service::create([
            'business_id' => $business->id,
            'name' => 'Badminton Court',
            'description' => 'Indoor badminton court with wooden flooring',
            'price' => 300,
            'duration' => 60,
            'is_active' => true,
            'sort_order' => 3,
            'booking_mode' => 'slot',
            'capacity' => 4,
            'inventory_units' => 2,
            'unit_label' => 'court',
            'price_unit' => 'hour',
            'has_fixed_slots' => true,
        ]);
    }

    private function createTaxiVehicles(Business $business): void
    {
        Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Sedan (Dzire/Etios)',
            'type' => 'car',
            'service_mode' => 'taxi',
            'seats' => 4,
            'base_fare' => 100,
            'fare_per_km' => 12,
            'requires_quote' => false,
            'min_km' => 1,
            'is_active' => true,
            'is_requestable' => true,
            'availability_status' => 'available',
            'sort_order' => 1,
        ]);

        Vehicle::create([
            'business_id' => $business->id,
            'name' => 'SUV (Innova/Xylo)',
            'type' => 'suv',
            'service_mode' => 'taxi',
            'seats' => 6,
            'base_fare' => 150,
            'fare_per_km' => 16,
            'requires_quote' => false,
            'min_km' => 1,
            'is_active' => true,
            'is_requestable' => true,
            'availability_status' => 'available',
            'sort_order' => 2,
        ]);

        Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Auto Rickshaw',
            'type' => 'auto',
            'service_mode' => 'taxi',
            'seats' => 3,
            'base_fare' => 30,
            'fare_per_km' => 8,
            'requires_quote' => false,
            'min_km' => 1,
            'is_active' => true,
            'is_requestable' => true,
            'availability_status' => 'available',
            'sort_order' => 3,
        ]);
    }

    private function createSharedVehicles(Business $business): void
    {
        Vehicle::create([
            'business_id' => $business->id,
            'name' => 'AC Bus (40 seats)',
            'type' => 'bus',
            'service_mode' => 'shared',
            'seats' => 40,
            'base_fare' => 500,
            'fare_per_km' => 5,
            'requires_quote' => true,
            'min_km' => 50,
            'is_active' => true,
            'is_requestable' => true,
            'availability_status' => 'available',
            'sort_order' => 1,
        ]);

        Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Non-AC Bus (50 seats)',
            'type' => 'bus',
            'service_mode' => 'shared',
            'seats' => 50,
            'base_fare' => 300,
            'fare_per_km' => 3,
            'requires_quote' => true,
            'min_km' => 50,
            'is_active' => true,
            'is_requestable' => true,
            'availability_status' => 'available',
            'sort_order' => 2,
        ]);
    }

    private function createRentalVehicles(Business $business): void
    {
        Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Self-Drive Hatchback',
            'type' => 'car',
            'service_mode' => 'rental',
            'seats' => 5,
            'base_fare' => 1500,
            'fare_per_km' => 0,
            'requires_quote' => false,
            'min_km' => 0,
            'is_active' => true,
            'is_requestable' => true,
            'availability_status' => 'available',
            'sort_order' => 1,
        ]);

        Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Self-Drive SUV',
            'type' => 'suv',
            'service_mode' => 'rental',
            'seats' => 7,
            'base_fare' => 3000,
            'fare_per_km' => 0,
            'requires_quote' => false,
            'min_km' => 0,
            'is_active' => true,
            'is_requestable' => true,
            'availability_status' => 'available',
            'sort_order' => 2,
        ]);
    }

    private function createGoodsVehicles(Business $business): void
    {
        Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Mini Truck (1 ton)',
            'type' => 'pickup',
            'service_mode' => 'goods',
            'seats' => 2,
            'capacity_value' => 1000,
            'capacity_unit' => 'kg',
            'base_fare' => 500,
            'fare_per_km' => 15,
            'requires_quote' => true,
            'min_km' => 10,
            'is_active' => true,
            'is_requestable' => true,
            'availability_status' => 'available',
            'sort_order' => 1,
        ]);

        Vehicle::create([
            'business_id' => $business->id,
            'name' => 'Truck (5 ton)',
            'type' => 'truck',
            'service_mode' => 'goods',
            'seats' => 2,
            'capacity_value' => 5000,
            'capacity_unit' => 'kg',
            'base_fare' => 2000,
            'fare_per_km' => 25,
            'requires_quote' => true,
            'min_km' => 20,
            'is_active' => true,
            'is_requestable' => true,
            'availability_status' => 'available',
            'sort_order' => 2,
        ]);
    }

    private function createSeatServices(Business $business): void
    {
        Service::create([
            'business_id' => $business->id,
            'name' => 'Main Hall - Premium Seats',
            'description' => 'Front row premium seating',
            'price' => 500,
            'duration' => 180,
            'is_active' => true,
            'sort_order' => 1,
            'booking_mode' => 'seat',
            'capacity' => 50,
            'inventory_units' => 50,
            'unit_label' => 'seat',
            'price_unit' => 'seat',
            'has_fixed_slots' => false,
        ]);

        Service::create([
            'business_id' => $business->id,
            'name' => 'Main Hall - Standard Seats',
            'description' => 'Standard seating',
            'price' => 300,
            'duration' => 180,
            'is_active' => true,
            'sort_order' => 2,
            'booking_mode' => 'seat',
            'capacity' => 100,
            'inventory_units' => 100,
            'unit_label' => 'seat',
            'price_unit' => 'seat',
            'has_fixed_slots' => false,
        ]);

        Service::create([
            'business_id' => $business->id,
            'name' => 'Balcony Seats',
            'description' => 'Balcony level seating',
            'price' => 200,
            'duration' => 180,
            'is_active' => true,
            'sort_order' => 3,
            'booking_mode' => 'seat',
            'capacity' => 80,
            'inventory_units' => 80,
            'unit_label' => 'seat',
            'price_unit' => 'seat',
            'has_fixed_slots' => false,
        ]);
    }
}