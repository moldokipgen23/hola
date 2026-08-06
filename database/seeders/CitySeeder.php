<?php

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['name' => 'Lamka (Churachandpur)', 'slug' => 'lamka-churachandpur', 'state' => 'Manipur', 'district' => 'Churachandpur', 'pincode' => '795128', 'is_home' => true, 'sort_order' => 1],
            ['name' => 'Imphal', 'slug' => 'imphal', 'state' => 'Manipur', 'district' => 'Imphal West', 'pincode' => '795001', 'sort_order' => 2],
            ['name' => 'Delhi', 'slug' => 'delhi', 'state' => 'Delhi', 'district' => 'New Delhi', 'pincode' => '110001', 'sort_order' => 3],
            ['name' => 'Kolkata', 'slug' => 'kolkata', 'state' => 'West Bengal', 'district' => 'Kolkata', 'pincode' => '700001', 'sort_order' => 4],
            ['name' => 'Guwahati', 'slug' => 'guwahati', 'state' => 'Assam', 'district' => 'Kamrup Metropolitan', 'pincode' => '781001', 'sort_order' => 5],
            ['name' => 'Shillong', 'slug' => 'shillong', 'state' => 'Meghalaya', 'district' => 'East Khasi Hills', 'pincode' => '793001', 'sort_order' => 6],
            ['name' => 'Aizawl', 'slug' => 'aizawl', 'state' => 'Mizoram', 'district' => 'Aizawl', 'pincode' => '796001', 'sort_order' => 7],
            ['name' => 'Silchar', 'slug' => 'silchar', 'state' => 'Assam', 'district' => 'Cachar', 'pincode' => '788001', 'sort_order' => 8],
            ['name' => 'Dimapur', 'slug' => 'dimapur', 'state' => 'Nagaland', 'district' => 'Dimapur', 'pincode' => '797112', 'sort_order' => 9],
            ['name' => 'Agartala', 'slug' => 'agartala', 'state' => 'Tripura', 'district' => 'West Tripura', 'pincode' => '799001', 'sort_order' => 10],
            ['name' => 'Mumbai', 'slug' => 'mumbai', 'state' => 'Maharashtra', 'district' => 'Mumbai City', 'pincode' => '400001', 'sort_order' => 11],
            ['name' => 'Bengaluru', 'slug' => 'bengaluru', 'state' => 'Karnataka', 'district' => 'Bengaluru Urban', 'pincode' => '560001', 'sort_order' => 12],
            ['name' => 'Hyderabad', 'slug' => 'hyderabad', 'state' => 'Telangana', 'district' => 'Hyderabad', 'pincode' => '500001', 'sort_order' => 13],
            ['name' => 'Pune', 'slug' => 'pune', 'state' => 'Maharashtra', 'district' => 'Pune', 'pincode' => '411001', 'sort_order' => 14],
            ['name' => 'Chennai', 'slug' => 'chennai', 'state' => 'Tamil Nadu', 'district' => 'Chennai', 'pincode' => '600001', 'sort_order' => 15],
            ['name' => 'Patna', 'slug' => 'patna', 'state' => 'Bihar', 'district' => 'Patna', 'pincode' => '800001', 'sort_order' => 16],
            ['name' => 'Kohima', 'slug' => 'kohima', 'state' => 'Nagaland', 'district' => 'Kohima', 'pincode' => '797001', 'sort_order' => 17],
            ['name' => 'Churachandpur', 'slug' => 'churachandpur', 'state' => 'Manipur', 'district' => 'Churachandpur', 'pincode' => '795128', 'sort_order' => 18],
        ];

        foreach ($cities as $city) {
            City::updateOrCreate(['slug' => $city['slug']], $city);
        }
    }
}
