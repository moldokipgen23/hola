<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IndiaCitySeeder extends Seeder
{
    /** [slug, name, state, district, pincode, latitude, longitude] */
    private const CITIES = [
        ['lamka-churachandpur', 'Lamka (Churachandpur)', 'Manipur', 'Churachandpur', '795128', 24.3333333, 93.6833333],
        ['imphal', 'Imphal', 'Manipur', 'Imphal West', '795001', 24.8170000, 93.9368000],
        ['thoubal', 'Thoubal', 'Manipur', 'Thoubal', '795138', 24.6333333, 93.9833333],
        ['bishnupur', 'Bishnupur', 'Manipur', 'Bishnupur', '795126', 24.6333333, 93.7666667],
        ['ukhrul', 'Ukhrul', 'Manipur', 'Ukhrul', '795142', 25.1000000, 94.3666667],
        ['senapati', 'Senapati', 'Manipur', 'Senapati', '795106', 25.2670000, 94.0330000],
        ['tamenglong', 'Tamenglong', 'Manipur', 'Tamenglong', '795141', 24.9830000, 93.4830000],
        ['chandel', 'Chandel', 'Manipur', 'Chandel', '795127', 24.3330000, 93.9830000],
        ['guwahati', 'Guwahati', 'Assam', 'Kamrup Metro', '781001', 26.1445000, 91.7362000],
        ['silchar', 'Silchar', 'Assam', 'Cachar', '788001', 24.8333333, 92.7789000],
        ['dimapur', 'Dimapur', 'Nagaland', 'Dimapur', '797112', 25.8995000, 93.7270000],
        ['kohima', 'Kohima', 'Nagaland', 'Kohima', '797001', 25.6751000, 94.1086000],
        ['aizawl', 'Aizawl', 'Mizoram', 'Aizawl', '796001', 23.7271000, 92.7176000],
        ['shillong', 'Shillong', 'Meghalaya', 'East Khasi Hills', '793001', 25.5788000, 91.8933000],
        ['agartala', 'Agartala', 'Tripura', 'West Tripura', '799001', 23.8315000, 91.2868000],
        ['itanagar', 'Itanagar', 'Arunachal Pradesh', 'Papum Pare', '791111', 27.0844000, 93.6053000],
        ['gangtok', 'Gangtok', 'Sikkim', 'East Sikkim', '737101', 27.3389000, 88.6065000],
        ['jorhat', 'Jorhat', 'Assam', 'Jorhat', '785001', 26.7505000, 94.2163000],
        ['dibrugarh', 'Dibrugarh', 'Assam', 'Dibrugarh', '786001', 27.4728000, 94.9120000],
        ['tezpur', 'Tezpur', 'Assam', 'Sonitpur', '784001', 26.6333333, 92.8000000],
        ['delhi', 'New Delhi', 'Delhi', 'New Delhi', '110001', 28.6139000, 77.2090000],
        ['mumbai', 'Mumbai', 'Maharashtra', 'Mumbai', '400001', 19.0760000, 72.8777000],
        ['bengaluru', 'Bengaluru', 'Karnataka', 'Bengaluru Urban', '560001', 12.9716000, 77.5946000],
        ['hyderabad', 'Hyderabad', 'Telangana', 'Hyderabad', '500001', 17.3850000, 78.4867000],
        ['chennai', 'Chennai', 'Tamil Nadu', 'Chennai', '600001', 13.0827000, 80.2707000],
        ['kolkata', 'Kolkata', 'West Bengal', 'Kolkata', '700001', 22.5726000, 88.3639000],
        ['ahmedabad', 'Ahmedabad', 'Gujarat', 'Ahmedabad', '380001', 23.0225000, 72.5714000],
        ['pune', 'Pune', 'Maharashtra', 'Pune', '411001', 18.5204000, 73.8567000],
        ['jaipur', 'Jaipur', 'Rajasthan', 'Jaipur', '302001', 26.9124000, 75.7873000],
        ['lucknow', 'Lucknow', 'Uttar Pradesh', 'Lucknow', '226001', 26.8467000, 80.9462000],
        ['kanpur', 'Kanpur', 'Uttar Pradesh', 'Kanpur Nagar', '208001', 26.4499000, 80.3319000],
        ['nagpur', 'Nagpur', 'Maharashtra', 'Nagpur', '440001', 21.1458000, 79.0882000],
        ['indore', 'Indore', 'Madhya Pradesh', 'Indore', '452001', 22.7196000, 75.8577000],
        ['bhopal', 'Bhopal', 'Madhya Pradesh', 'Bhopal', '462001', 23.2599000, 77.4126000],
        ['patna', 'Patna', 'Bihar', 'Patna', '800001', 25.5941000, 85.1376000],
        ['vadodara', 'Vadodara', 'Gujarat', 'Vadodara', '390001', 22.3072000, 73.1812000],
        ['ludhiana', 'Ludhiana', 'Punjab', 'Ludhiana', '141001', 30.9010000, 75.8573000],
        ['agra', 'Agra', 'Uttar Pradesh', 'Agra', '282001', 27.1767000, 78.0081000],
        ['surat', 'Surat', 'Gujarat', 'Surat', '395003', 21.1702000, 72.8311000],
        ['varanasi', 'Varanasi', 'Uttar Pradesh', 'Varanasi', '221001', 25.3176000, 82.9739000],
        ['srinagar', 'Srinagar', 'Jammu & Kashmir', 'Srinagar', '190001', 34.0837000, 74.7973000],
        ['amritsar', 'Amritsar', 'Punjab', 'Amritsar', '143001', 31.6340000, 74.8723000],
        ['rancchi', 'Ranchi', 'Jharkhand', 'Ranchi', '834001', 23.3441000, 85.3096000],
        ['coimbatore', 'Coimbatore', 'Tamil Nadu', 'Coimbatore', '641001', 11.0168000, 76.9558000],
        ['jodhpur', 'Jodhpur', 'Rajasthan', 'Jodhpur', '342001', 26.2389000, 73.0243000],
        ['chandigarh', 'Chandigarh', 'Chandigarh', 'Chandigarh', '160001', 30.7333000, 76.7794000],
        ['mysuru', 'Mysuru', 'Karnataka', 'Mysuru', '570001', 12.2958000, 76.6394000],
        ['vijayawada', 'Vijayawada', 'Andhra Pradesh', 'Krishna', '520001', 16.5062000, 80.6480000],
        ['visakhapatnam', 'Visakhapatnam', 'Andhra Pradesh', 'Visakhapatnam', '530001', 17.6868000, 83.2185000],
        ['madurai', 'Madurai', 'Tamil Nadu', 'Madurai', '625001', 9.9252000, 78.1198000],
        ['thiruvananthapuram', 'Thiruvananthapuram', 'Kerala', 'Thiruvananthapuram', '695001', 8.5241000, 76.9366000],
        ['kochi', 'Kochi', 'Kerala', 'Ernakulam', '682001', 9.9312000, 76.2673000],
        ['dehradun', 'Dehradun', 'Uttarakhand', 'Dehradun', '248001', 30.3165000, 78.0322000],
        ['udaipur', 'Udaipur', 'Rajasthan', 'Udaipur', '313001', 24.5854000, 73.7125000],
        ['bhubaneswar', 'Bhubaneswar', 'Odisha', 'Khordha', '751001', 20.2961000, 85.8245000],
        ['raipur', 'Raipur', 'Chhattisgarh', 'Raipur', '492001', 21.2514000, 81.6296000],
        ['jammu', 'Jammu', 'Jammu & Kashmir', 'Jammu', '180001', 32.7266000, 74.8570000],
        ['panaji', 'Panaji', 'Goa', 'North Goa', '403001', 15.4909000, 73.8278000],
        ['portblair', 'Port Blair', 'Andaman & Nicobar', 'South Andaman', '744101', 11.6234000, 92.7265000],
    ];

    public function run(): void
    {
        $now = now();
        foreach (self::CITIES as $i => $city) {
            [$slug, $name, $state, $district, $pincode, $lat, $lng] = $city;
            DB::table('cities')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'state' => $state,
                    'district' => $district,
                    'pincode' => $pincode,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'is_active' => true,
                    'is_home' => $slug === 'lamka-churachandpur',
                    'sort_order' => $i,
                    'updated_at' => $now,
                ]
            );
        }
        $this->command?->info('IndiaCitySeeder: '.count(self::CITIES).' cities upserted.');
    }
}
