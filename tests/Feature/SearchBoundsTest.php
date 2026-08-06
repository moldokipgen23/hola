<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\AgentSkillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchBoundsTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AgentSkillService
    {
        return new AgentSkillService;
    }

    public function test_district_bounds_fall_back_to_default_when_settings_blank(): void
    {
        Setting::set('search_bounds_north', '', 'search');
        Setting::set('search_bounds_south', '', 'search');
        Setting::set('search_bounds_east', '', 'search');
        Setting::set('search_bounds_west', '', 'search');

        $this->assertSame(AgentSkillService::DISTRICT_BOUNDS, $this->service()->districtBounds());
    }

    public function test_district_bounds_reflect_configured_settings(): void
    {
        Setting::set('search_bounds_north', '24.5000', 'search');
        Setting::set('search_bounds_south', '24.1000', 'search');
        Setting::set('search_bounds_east', '94.0000', 'search');
        Setting::set('search_bounds_west', '93.4000', 'search');

        $this->assertSame([
            'north' => 24.5,
            'south' => 24.1,
            'east' => 94.0,
            'west' => 93.4,
        ], $this->service()->districtBounds());
    }

    public function test_district_bounds_ignore_invalid_values(): void
    {
        Setting::set('search_bounds_north', '999', 'search');
        Setting::set('search_bounds_south', 'abc', 'search');
        Setting::set('search_bounds_east', '94.0000', 'search');
        Setting::set('search_bounds_west', '', 'search');

        $bounds = $this->service()->districtBounds();

        $this->assertSame(AgentSkillService::DISTRICT_BOUNDS['north'], $bounds['north']);
        $this->assertSame(AgentSkillService::DISTRICT_BOUNDS['south'], $bounds['south']);
        $this->assertSame(94.0, $bounds['east']);
        $this->assertSame(AgentSkillService::DISTRICT_BOUNDS['west'], $bounds['west']);
    }

    public function test_admin_can_persist_search_bounds(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->post(route('admin.autopilot.location'), [
                'search_district' => 'Imphal',
                'search_state' => 'Manipur',
                'search_zipcodes' => '795001',
                'search_areas' => 'Imphal, Khwai',
                'search_bounds_north' => '25.0000',
                'search_bounds_south' => '24.5000',
                'search_bounds_east' => '94.2000',
                'search_bounds_west' => '93.6000',
            ])
            ->assertSessionHas('success');

        $this->assertSame('Imphal', Setting::get('search_district'));
        $this->assertSame('25.0000', Setting::get('search_bounds_north'));
        $this->assertSame('24.5000', Setting::get('search_bounds_south'));
        $this->assertSame('94.2000', Setting::get('search_bounds_east'));
        $this->assertSame('93.6000', Setting::get('search_bounds_west'));

        $this->assertSame([
            'north' => 25.0,
            'south' => 24.5,
            'east' => 94.2,
            'west' => 93.6,
        ], $this->service()->districtBounds());
    }
}
