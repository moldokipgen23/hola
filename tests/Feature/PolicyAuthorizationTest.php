<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\MediaLibrary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PolicyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_config_update_forbidden_for_non_owner_allowed_for_owner_and_admin(): void
    {
        [$owner, $business] = $this->ownedBusiness();
        $otherOwner = User::factory()->create(['role' => 'owner']);

        Sanctum::actingAs($otherOwner);
        $this->putJson("/api/businesses/{$business->id}/delivery-config", [
            'delivery_radius_km' => 5,
        ])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->putJson("/api/businesses/{$business->id}/delivery-config", [
            'delivery_radius_km' => 5,
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.business_id', $business->id);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $this->putJson("/api/businesses/{$business->id}/delivery-config", [
            'delivery_radius_km' => 8,
        ])->assertOk();
    }

    public function test_media_delete_forbidden_for_non_owner_allowed_for_owner(): void
    {
        Storage::fake('public');
        [$owner, $business] = $this->ownedBusiness();
        $otherOwner = User::factory()->create(['role' => 'owner']);

        $media = MediaLibrary::create([
            'user_id' => $owner->id,
            'business_id' => $business->id,
            'filename' => 'owned.jpg',
            'original_filename' => 'owned.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 100,
            'path' => 'owned.jpg',
            'disk' => 'public',
            'category' => 'general',
        ]);

        Sanctum::actingAs($otherOwner);
        $this->deleteJson("/api/media/{$media->id}")->assertForbidden();
        $this->assertDatabaseHas('media_library', ['id' => $media->id]);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/media/{$media->id}")->assertOk();
        $this->assertDatabaseMissing('media_library', ['id' => $media->id]);
    }

    public function test_vendor_web_business_update_forbidden_for_non_owner_allowed_for_owner(): void
    {
        [$owner, $business] = $this->ownedBusiness();
        $otherOwner = User::factory()->create(['role' => 'owner']);

        $this->actingAs($otherOwner);
        $this->put("/vendor/businesses/{$business->id}", [
            'name' => 'Hijacked Name',
        ])->assertForbidden();
        $this->assertDatabaseMissing('businesses', ['id' => $business->id, 'name' => 'Hijacked Name']);

        $this->actingAs($owner);
        $this->put("/vendor/businesses/{$business->id}", [
            'name' => 'Legit Update',
        ])->assertRedirect(route('vendor.businesses'));
        $this->assertDatabaseHas('businesses', ['id' => $business->id, 'name' => 'Legit Update']);
    }

    private function ownedBusiness(): array
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $category = Category::firstOrFail();
        $business = Business::create([
            'created_by' => $owner->id,
            'category_id' => $category->id,
            'name' => 'Secure Vendor '.str()->random(8),
            'slug' => 'secure-vendor-'.str()->lower(str()->random(8)),
            'address' => 'Test address',
            'phone' => '9876543210',
            'is_active' => true,
            'enabled_modules' => ['catalog' => true, 'orders' => true],
        ]);

        return [$owner, $business];
    }
}
