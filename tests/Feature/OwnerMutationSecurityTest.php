<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnerMutationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_configuration_requires_authentication_and_business_ownership(): void
    {
        [$owner, $business] = $this->ownedBusiness();
        $otherOwner = User::factory()->create(['role' => 'owner']);

        $this->putJson("/api/businesses/{$business->id}/delivery-config", [
            'delivery_radius_km' => 5,
        ])->assertUnauthorized();

        Sanctum::actingAs($otherOwner);
        $this->putJson("/api/businesses/{$business->id}/delivery-config", [
            'delivery_radius_km' => 5,
        ])->assertForbidden();

        Sanctum::actingAs($owner);
        $this->putJson("/api/businesses/{$business->id}/delivery-config", [
            'delivery_radius_km' => 5,
            'is_active' => true,
        ])->assertOk()->assertJsonPath('data.business_id', $business->id);
    }

    public function test_media_endpoints_require_authentication_and_reject_cross_tenant_uploads(): void
    {
        Storage::fake('public');
        [$owner, $business] = $this->ownedBusiness();
        $otherOwner = User::factory()->create(['role' => 'owner']);

        $this->getJson('/api/media')->assertUnauthorized();
        $this->postJson('/api/media/upload')->assertUnauthorized();

        Sanctum::actingAs($otherOwner);
        $this->post('/api/media/upload', [
            'business_id' => $business->id,
            'category' => 'general',
            'file' => UploadedFile::fake()->image('business.jpg'),
        ], ['Accept' => 'application/json'])->assertNotFound();

        Sanctum::actingAs($owner);
        $response = $this->post('/api/media/upload', [
            'business_id' => $business->id,
            'category' => 'general',
            'file' => UploadedFile::fake()->image('business.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.business_id', $business->id);
        Storage::disk('public')->assertExists($response->json('data.path'));
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
