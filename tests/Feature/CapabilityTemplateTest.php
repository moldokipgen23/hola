<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\CapabilityTemplate;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapabilityTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_turf_and_sports_template_applies_bookings_turf_experience_and_request_mode(): void
    {
        $business = $this->business();
        $template = CapabilityTemplate::create([
            'name' => 'Turf & Sports',
            'slug' => 'turf',
            'business_type' => 'book',
            'description' => 'Customers can book time slots for courts or grounds.',
            'enabled_modules' => ['bookings' => true, 'turf' => true],
            'enabled_experiences' => ['turf', 'directory'],
            'default_availability' => ['mode' => 'request'],
        ]);

        $template->applyTo($business);
        $business->refresh();

        $this->assertTrue($business->hasModule('bookings'));
        $this->assertTrue($business->hasModule('turf'));
        $this->assertSame(['turf', 'directory'], $business->enabled_experiences);
        $this->assertSame('turf', $business->primary_experience);
        $this->assertSame('request', $business->experience_config['turf']['availability_mode']);
    }

    public function test_essentials_only_template_applies_without_rich_fields(): void
    {
        $business = $this->business();
        $template = CapabilityTemplate::create([
            'name' => 'Minimal Listing',
            'slug' => 'minimal-listing',
            'business_type' => 'discover',
            'enabled_modules' => ['bookings' => true],
            'enabled_experiences' => ['directory'],
            'default_availability' => ['mode' => 'contact'],
        ]);

        $this->assertNull($template->fulfilment_options);
        $this->assertNull($template->required_fields);
        $this->assertNull($template->vendor_menu_config);

        $template->applyTo($business);
        $business->refresh();

        $this->assertTrue($business->hasModule('bookings'));
        $this->assertSame(['directory'], $business->enabled_experiences);
        $this->assertSame('directory', $business->primary_experience);
        $this->assertSame('contact', $business->experience_config['directory']['availability_mode']);
    }

    public function test_admin_can_create_essentials_only_template(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->post(route('admin.capability-templates.store'), [
                'name' => 'Court Rental',
                'enabled_modules' => ['bookings', 'turf'],
                'enabled_experiences' => ['turf', 'directory'],
            ])
            ->assertRedirect(route('admin.capability-templates'));

        $template = CapabilityTemplate::where('slug', 'court-rental')->firstOrFail();
        $this->assertNull($template->business_type);
        $this->assertSame(['bookings' => true, 'turf' => true], $template->enabled_modules);
        $this->assertSame(['turf', 'directory'], $template->enabled_experiences);
        $this->assertNull($template->default_availability);
        $this->assertNull($template->fulfilment_options);
        $this->assertTrue($template->is_active);
    }

    private function business(array $overrides = []): Business
    {
        $category = Category::firstOrFail();

        return Business::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Template Business '.str()->random(8),
            'slug' => 'template-business-'.str()->lower(str()->random(8)),
            'address' => 'Test address',
            'phone' => '9876543210',
            'is_active' => true,
            'enabled_modules' => [],
        ], $overrides));
    }
}
