<?php

namespace Database\Seeders;

use App\Models\CapabilityTemplate;
use App\Models\Category;
use App\Models\World;
use Illuminate\Database\Seeder;

class WorldSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => 'Grocery Store',
                'slug' => 'grocery',
                'business_type' => 'shop',
                'description' => 'Customers can browse products and send pickup or delivery orders.',
                'enabled_modules' => ['catalog' => true, 'orders' => true, 'inventory' => true],
                'enabled_experiences' => ['retail', 'directory'],
                'default_availability' => ['mode' => 'request'],
                'fulfilment_options' => ['pickup', 'vendor_delivery', 'cod'],
            ],
            [
                'name' => 'Restaurant',
                'slug' => 'restaurant',
                'business_type' => 'shop',
                'description' => 'Customers can view your menu and send food orders.',
                'enabled_modules' => ['catalog' => true, 'orders' => true],
                'enabled_experiences' => ['restaurant', 'directory'],
                'default_availability' => ['mode' => 'request'],
                'fulfilment_options' => ['pickup', 'vendor_delivery', 'cod'],
            ],
            [
                'name' => 'Pharmacy',
                'slug' => 'pharmacy',
                'business_type' => 'shop',
                'description' => 'Customers can browse medicines and send requests.',
                'enabled_modules' => ['catalog' => true, 'orders' => true, 'inventory' => true],
                'enabled_experiences' => ['retail', 'directory'],
                'default_availability' => ['mode' => 'request'],
                'fulfilment_options' => ['pickup', 'vendor_delivery', 'prescription_upload'],
            ],
            [
                'name' => 'Retail Shop',
                'slug' => 'retail',
                'business_type' => 'shop',
                'description' => 'Customers can browse products and place orders.',
                'enabled_modules' => ['catalog' => true, 'orders' => true, 'inventory' => true],
                'enabled_experiences' => ['retail', 'directory'],
                'default_availability' => ['mode' => 'request'],
                'fulfilment_options' => ['pickup', 'vendor_delivery', 'cod'],
            ],
            [
                'name' => 'Salon & Beauty',
                'slug' => 'salon',
                'business_type' => 'book',
                'description' => 'Customers can select a service and request a time.',
                'enabled_modules' => ['bookings' => true],
                'enabled_experiences' => ['appointment', 'directory'],
                'default_availability' => ['mode' => 'request'],
                'fulfilment_options' => ['visit_venue', 'pay_at_venue'],
            ],
            [
                'name' => 'Bookings (generic)',
                'slug' => 'bookings',
                'business_type' => 'book',
                'description' => 'Accept booking requests for services, rooms, slots or seats.',
                'enabled_modules' => ['bookings' => true],
                'enabled_experiences' => ['directory'],
                'default_availability' => ['mode' => 'request'],
                'fulfilment_options' => ['visit_venue', 'pay_at_venue'],
            ],
            [
                'name' => 'Hotel & Stay',
                'slug' => 'hotel',
                'business_type' => 'book',
                'description' => 'Customers can choose dates and request a room.',
                'enabled_modules' => ['bookings' => true],
                'enabled_experiences' => ['stay', 'directory'],
                'default_availability' => ['mode' => 'request'],
                'fulfilment_options' => ['visit_venue', 'pay_at_property'],
            ],
            [
                'name' => 'Turf & Sports',
                'slug' => 'turf',
                'business_type' => 'book',
                'description' => 'Customers can book time slots for courts or grounds.',
                'enabled_modules' => ['bookings' => true, 'turf' => true],
                'enabled_experiences' => ['turf', 'directory'],
                'default_availability' => ['mode' => 'request'],
                'fulfilment_options' => ['visit_venue', 'pay_at_venue'],
            ],
            [
                'name' => 'Taxi Service',
                'slug' => 'taxi',
                'business_type' => 'ride',
                'description' => 'Customers can enter pickup and destination to request a ride.',
                'enabled_modules' => ['transport' => true],
                'enabled_experiences' => ['taxi', 'directory'],
                'default_availability' => ['mode' => 'request'],
                'fulfilment_options' => ['cash_to_driver'],
            ],
            [
                'name' => 'Event Venue',
                'slug' => 'event',
                'business_type' => 'book',
                'description' => 'Customers can browse events and book seats.',
                'enabled_modules' => ['bookings' => true, 'turf' => true],
                'enabled_experiences' => ['seat_event', 'turf', 'directory'],
                'default_availability' => ['mode' => 'request'],
                'fulfilment_options' => ['visit_venue', 'pay_at_venue'],
            ],
            [
                'name' => 'General Business',
                'slug' => 'general',
                'business_type' => 'discover',
                'description' => 'Basic listing with contact information.',
                'enabled_modules' => ['catalog' => false, 'orders' => false, 'bookings' => false],
                'enabled_experiences' => ['directory'],
                'default_availability' => ['mode' => 'contact'],
                'fulfilment_options' => ['contact_only'],
            ],
        ];

        foreach ($templates as $template) {
            CapabilityTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }

        $worlds = [
            [
                'name' => 'Shopping',
                'slug' => 'shop',
                'icon' => 'store',
                'description' => 'Browse and buy products from local stores',
                'sort_order' => 1,
                'is_active' => true,
                'is_primary' => true,
                'nav_config' => [
                    'search_placeholder' => 'Search products, stores...',
                ],
            ],
            [
                'name' => 'Ride',
                'slug' => 'ride',
                'icon' => 'directions_car',
                'description' => 'Get around town or ship goods',
                'sort_order' => 2,
                'is_active' => true,
                'is_primary' => true,
                'nav_config' => [
                    'search_placeholder' => 'Enter pickup location...',
                ],
            ],
            [
                'name' => 'Booking',
                'slug' => 'book',
                'icon' => 'calendar_today',
                'description' => 'Reserve rooms, slots, and appointments',
                'sort_order' => 3,
                'is_active' => true,
                'is_primary' => true,
                'nav_config' => [
                    'search_placeholder' => 'What do you want to book?',
                ],
            ],
            [
                'name' => 'Directory',
                'slug' => 'discover',
                'icon' => 'explore',
                'description' => 'Find businesses, services, and places',
                'sort_order' => 4,
                'is_active' => true,
                'is_primary' => true,
                'nav_config' => [
                    'search_placeholder' => 'Search businesses, services...',
                ],
            ],
        ];

        foreach ($worlds as $world) {
            World::updateOrCreate(
                ['slug' => $world['slug']],
                $world
            );
        }

        $this->seedLevelOneCategories();
    }

    /**
     * Initial level-1 categories per world — these become the app sub-tabs
     * (derived, not hard-coded). Idempotent via slug.
     */
    private function seedLevelOneCategories(): void
    {
        $levelOne = [
            'shop' => ['module_type' => 'ordering', 'categories' => [
                ['Grocery', 'grocery'],
                ['Food', 'food'],
                ['Medicine', 'medicine'],
                ['General Shopping', 'general-shopping'],
            ]],
            'book' => ['module_type' => 'booking', 'categories' => [
                ['Taxi', 'taxi'],
                ['Hotel', 'hotel'],
                ['Turf', 'turf'],
                ['Salon', 'salon'],
                ['Doctor', 'doctor'],
                ['Events', 'events'],
            ]],
            'discover' => ['module_type' => 'directory', 'categories' => [
                ['Businesses', 'businesses'],
                ['Professionals', 'professionals'],
                ['Institutions', 'institutions'],
                ['Places', 'places'],
            ]],
        ];

        foreach ($levelOne as $worldSlug => $config) {
            $world = World::where('slug', $worldSlug)->first();
            if (! $world) {
                continue;
            }

            foreach ($config['categories'] as [$name, $slug]) {
                Category::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'world_id' => $world->id,
                        'parent_id' => null,
                        'level' => 1,
                        'module_type' => $config['module_type'],
                        'is_active' => true,
                        'is_canonical' => false,
                        'launch_phase' => 'phase1',
                    ]
                );
            }
        }
    }
}
