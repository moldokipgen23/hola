<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\LaunchPhase1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBusinessOwnersTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name, string $moduleType): Category
    {
        return Category::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'module_type' => $moduleType,
            'is_canonical' => true,
            'is_active' => true,
        ]);
    }

    private function ownedBusiness(User $owner, Category $category, array $overrides = []): Business
    {
        return Business::create(array_merge([
            'name' => 'Owner Shop '.uniqid(),
            'slug' => 'owner-shop-'.uniqid(),
            'category_id' => $category->id,
            'address' => 'Test street',
            'created_by' => $owner->id,
            'claim_status' => 'claimed',
            'verification_status' => 'verified',
            'enabled_modules' => ['catalog' => true, 'orders' => true],
        ], $overrides));
    }

    public function test_business_owners_page_lists_owned_businesses_with_owner(): void
    {
        $this->seed(LaunchPhase1Seeder::class);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $owner = User::factory()->create(['role' => 'owner', 'name' => 'Jane Owner']);
        $category = $this->category('Restaurants', 'ordering');
        $this->ownedBusiness($owner, $category, ['name' => 'Taste of Manipur']);

        $this->actingAs($admin)
            ->get(route('admin.vendors'))
            ->assertOk()
            ->assertSee('Taste of Manipur')
            ->assertSee('Jane Owner')
            ->assertSee('Shopping')
            ->assertSee('Restaurants')
            ->assertSee('Verified');

        $unowned = $this->category('Clinics', 'booking');
        Business::create([
            'name' => 'Unclaimed Clinic',
            'slug' => 'unclaimed-clinic-'.uniqid(),
            'category_id' => $unowned->id,
            'address' => 'No owner',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.vendors'))
            ->assertOk()
            ->assertDontSee('Unclaimed Clinic');
    }

    public function test_business_owners_page_filters_by_type_category_and_verification(): void
    {
        $this->seed(LaunchPhase1Seeder::class);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $owner = User::factory()->create(['role' => 'owner']);

        $salonCat = $this->category('Salons', 'booking');
        $retailCat = $this->category('Groceries', 'ordering');
        $salon = $this->ownedBusiness($owner, $salonCat, [
            'name' => 'Glamour Salon',
            'enabled_modules' => ['bookings' => true],
            'verification_status' => 'pending',
        ]);
        $grocery = $this->ownedBusiness($owner, $retailCat, ['name' => 'City Grocery']);

        $this->actingAs($admin)
            ->get(route('admin.vendors', ['type' => 'booking']))
            ->assertOk()
            ->assertSee('Glamour Salon')
            ->assertDontSee('City Grocery');

        $this->actingAs($admin)
            ->get(route('admin.vendors', ['category_id' => $retailCat->id]))
            ->assertOk()
            ->assertSee('City Grocery')
            ->assertDontSee('Glamour Salon');

        $this->actingAs($admin)
            ->get(route('admin.vendors', ['verification_status' => 'pending']))
            ->assertOk()
            ->assertSee('Glamour Salon')
            ->assertDontSee('City Grocery');

        $this->assertNotNull($salon->id);
        $this->assertNotNull($grocery->id);
    }

    public function test_business_owners_page_searches_owner_or_business_name(): void
    {
        $this->seed(LaunchPhase1Seeder::class);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $owner = User::factory()->create(['role' => 'owner', 'name' => 'Zara Founder']);
        $category = $this->category('Shops', 'ordering');
        $this->ownedBusiness($owner, $category, ['name' => 'Zara Boutique']);

        $this->actingAs($admin)
            ->get(route('admin.vendors', ['search' => 'Zara']))
            ->assertOk()
            ->assertSee('Zara Boutique');
    }

    public function test_verify_and_suspend_actions_are_available_from_list(): void
    {
        $this->seed(LaunchPhase1Seeder::class);
        $admin = User::factory()->create(['role' => 'super_admin']);
        $owner = User::factory()->create(['role' => 'owner']);
        $category = $this->category('Salons', 'booking');
        $business = $this->ownedBusiness($owner, $category, ['verification_status' => 'pending']);

        $this->actingAs($admin)
            ->get(route('admin.vendors'))
            ->assertOk()
            ->assertSee(route('admin.businesses.verify', $business->id))
            ->assertSee(route('admin.users.ban', $owner->id));
    }
}
