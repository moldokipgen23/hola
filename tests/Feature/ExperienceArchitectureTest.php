<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessClassification;
use App\Models\CapabilityTemplate;
use App\Models\Category;
use App\Models\Product;
use App\Models\World;
use App\Services\Experience\BusinessExperienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_detail_returns_prototype_data_for_a_ready_experience(): void
    {
        $business = $this->business([
            'enabled_modules' => ['catalog' => true, 'orders' => true],
            'enabled_experiences' => ['retail', 'directory'],
            'primary_experience' => 'retail',
        ]);
        Product::create([
            'business_id' => $business->id,
            'name' => 'Local Product',
            'slug' => 'local-product-'.str()->lower(str()->random(6)),
            'price' => 100,
            'is_active' => true,
        ]);

        $this->getJson("/api/businesses/{$business->slug}")
            ->assertOk()
            ->assertJsonPath('business.primary_action.type', 'request_order')
            ->assertJsonPath('prototype_data.retail.categories_count', 0)
            ->assertJsonCount(1, 'prototype_data.retail.top_products');
    }

    public function test_capability_template_applies_experiences_and_availability(): void
    {
        $business = $this->business();
        $template = CapabilityTemplate::create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'business_type' => 'shop',
            'enabled_modules' => ['catalog' => true, 'orders' => true],
            'enabled_experiences' => ['restaurant', 'directory'],
            'default_availability' => ['mode' => 'request'],
        ]);

        $template->applyTo($business);
        $business->refresh();

        $this->assertSame('restaurant', $business->primary_experience);
        $this->assertSame(['restaurant', 'directory'], $business->enabled_experiences);
        $this->assertSame('request', $business->experience_config['restaurant']['availability_mode']);
        $this->assertTrue($business->hasModule('orders'));
    }

    public function test_merged_service_reports_primary_experience_readiness(): void
    {
        $business = $this->business([
            'enabled_modules' => ['catalog' => true, 'orders' => true],
            'enabled_experiences' => ['retail', 'directory'],
            'primary_experience' => 'retail',
        ]);
        Product::create([
            'business_id' => $business->id,
            'name' => 'Ready Product',
            'slug' => 'ready-product-'.str()->lower(str()->random(6)),
            'price' => 50,
            'is_active' => true,
        ]);

        $service = app(BusinessExperienceService::class);

        $this->assertTrue($service->getPrimaryExperienceReadiness($business)['ready']);

        $bare = $this->business([
            'enabled_modules' => ['catalog' => true, 'orders' => true],
            'enabled_experiences' => ['retail', 'directory'],
            'primary_experience' => 'retail',
        ]);
        $this->assertFalse($service->getPrimaryExperienceReadiness($bare)['ready']);
    }

    public function test_merged_service_scope_ready_only_filters_by_module_and_activity(): void
    {
        $ready = $this->business(['enabled_modules' => ['catalog' => true]]);
        $inactive = $this->business(['enabled_modules' => ['catalog' => true], 'is_active' => false]);
        $noModule = $this->business(['enabled_modules' => []]);

        $service = app(BusinessExperienceService::class);
        $ids = $service->scopeReadyOnly(Business::query(), 'retail')->pluck('id');

        $this->assertTrue($ids->contains($ready->id));
        $this->assertFalse($ids->contains($inactive->id));
        $this->assertFalse($ids->contains($noModule->id));
    }

    public function test_merged_service_set_availability_mode_requires_supported_mode(): void
    {
        $service = app(BusinessExperienceService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->setAvailabilityMode($this->business(), 'retail', 'nonsense');
    }

    public function test_business_worlds_are_resolved_through_classified_categories(): void
    {
        $world = World::create(['name' => 'Shop', 'slug' => 'shop']);
        $category = Category::firstOrFail();
        $category->update(['world_id' => $world->id]);
        $business = $this->business(['category_id' => $category->id]);
        BusinessClassification::create([
            'business_id' => $business->id,
            'category_id' => $category->id,
            'is_primary' => true,
            'is_active' => true,
            'source' => 'test',
        ]);

        $this->assertTrue($business->hasWorld('shop'));
        $this->assertSame(['shop'], $business->worlds()->pluck('slug')->all());
    }

    private function business(array $overrides = []): Business
    {
        $category = Category::firstOrFail();

        return Business::create(array_merge([
            'category_id' => $category->id,
            'name' => 'Experience Business '.str()->random(8),
            'slug' => 'experience-business-'.str()->lower(str()->random(8)),
            'address' => 'Test address',
            'phone' => '9876543210',
            'is_active' => true,
            'enabled_modules' => [],
        ], $overrides));
    }
}
