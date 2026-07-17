<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoVendorsSeeder extends Seeder
{
    public function run(): void
    {
        $pincode = \App\Models\Pincode::where('state', 'Manipur')->where('serviceable', true)->first();
        if (! $pincode) {
            $this->command->error('No serviceable pincode found in Manipur. Run ImportPincodes first.');
            return;
        }

        $area = Area::first() ?? Area::create([
            'name' => 'Lamka',
            'slug' => 'lamka',
            'district' => 'Churachandpur',
            'state' => 'Manipur',
            'is_active' => true,
        ]);

        $vendors = [
            [
                'user' => ['name' => 'Demo Food Vendor', 'email' => 'food@demo.hola', 'phone' => '9000000001', 'password' => 'password'],
                'business' => ['name' => 'Tasty Bites', 'category_id' => 1, 'module_type' => 'ordering'],
            ],
            [
                'user' => ['name' => 'Demo Hotel Vendor', 'email' => 'hotel@demo.hola', 'phone' => '9000000002', 'password' => 'password'],
                'business' => ['name' => 'Cozy Stay Inn', 'category_id' => 2, 'module_type' => 'booking'],
            ],
            [
                'user' => ['name' => 'Demo Shop Vendor', 'email' => 'shop@demo.hola', 'phone' => '9000000003', 'password' => 'password'],
                'business' => ['name' => 'Lamka General Store', 'category_id' => 5, 'module_type' => 'ordering'],
            ],
        ];

        foreach ($vendors as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['user']['email']],
                [
                    'name' => $data['user']['name'],
                    'phone' => $data['user']['phone'],
                    'password' => Hash::make($data['user']['password']),
                    'role' => 'owner',
                    'email_verified_at' => now(),
                ]
            );

            $slug = Str::slug($data['business']['name']);
            if (Business::withTrashed()->where('slug', $slug)->exists()) {
                $slug .= '-' . Str::random(4);
            }

            $modules = match ($data['business']['module_type']) {
                'ordering' => ['catalog' => true, 'orders' => true, 'bookings' => false, 'inventory' => true],
                'booking' => ['catalog' => true, 'bookings' => true, 'orders' => false, 'inventory' => false],
                default => ['catalog' => true, 'bookings' => false, 'orders' => false, 'inventory' => false],
            };

            $business = Business::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $data['business']['name'],
                    'category_id' => $data['business']['category_id'],
                    'area_id' => $area->id,
                    'address' => 'Lamka Bazar, Churachandpur, Manipur',
                    'locality' => 'Lamka',
                    'district' => 'Churachandpur',
                    'pincode' => $pincode->pincode,
                    'state' => 'Manipur',
                    'latitude' => 24.3350,
                    'longitude' => 93.7050,
                    'phone' => $data['user']['phone'],
                    'description' => "Demo {$data['business']['module_type']} business for testing.",
                    'created_by' => $user->id,
                    'claim_status' => 'claimed',
                    'verification_status' => 'verified',
                    'is_active' => true,
                    'is_featured' => true,
                    'enabled_modules' => $modules,
                    'service_type' => $data['business']['module_type'] === 'ordering' ? 'buyable' : 'bookable',
                    'delivery_radius_km' => 10,
                ]
            );

            $this->command->info("Created: {$data['business']['name']} — vendor: {$data['user']['email']} / password");
        }
    }
}
