<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hidden_reviews_are_excluded_from_public_index(): void
    {
        [$business] = $this->business();
        $approved = Review::create([
            'user_id' => User::factory()->create()->id,
            'business_id' => $business->id,
            'rating' => 5,
            'comment' => 'Great place',
            'status' => 'approved',
        ]);
        Review::create([
            'user_id' => User::factory()->create()->id,
            'business_id' => $business->id,
            'rating' => 1,
            'comment' => 'Spam spam spam',
            'status' => 'hidden',
        ]);

        $response = $this->getJson("/api/businesses/{$business->id}/reviews")
            ->assertOk();

        $this->assertCount(1, $response->json('reviews.data'));
        $this->assertSame('Great place', $response->json('reviews.data.0.comment'));
        $this->assertSame(1, (int) $response->json('stats.count'));
        $this->assertSame(5.0, (float) $response->json('stats.average'));
    }

    public function test_admin_can_moderate_review_via_api(): void
    {
        [$business] = $this->business();
        $review = Review::create([
            'user_id' => User::factory()->create()->id,
            'business_id' => $business->id,
            'rating' => 2,
            'comment' => 'Needs moderation',
        ]);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/reviews/{$review->id}/moderate", [
                'status' => 'hidden',
                'reason' => 'Spam',
            ])
            ->assertOk()
            ->assertJsonPath('review.status', 'hidden');

        $review->refresh();
        $this->assertSame('hidden', $review->status);
        $this->assertSame('Spam', $review->moderation_reason);
        $this->assertNotNull($review->flagged_at);
    }

    public function test_admin_moderation_queue_returns_counts(): void
    {
        [$business] = $this->business();
        Review::create(['user_id' => User::factory()->create()->id, 'business_id' => $business->id, 'rating' => 3, 'status' => 'pending']);
        Review::create(['user_id' => User::factory()->create()->id, 'business_id' => $business->id, 'rating' => 4, 'status' => 'approved']);
        Review::create(['user_id' => User::factory()->create()->id, 'business_id' => $business->id, 'rating' => 2, 'status' => 'hidden']);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/reviews/moderation?status=pending')
            ->assertOk()
            ->assertJsonPath('counts.pending', 1)
            ->assertJsonPath('counts.approved', 1)
            ->assertJsonPath('counts.hidden', 1)
            ->assertJsonCount(1, 'reviews.data');
    }

    public function test_review_photo_can_be_uploaded_and_moderation_flags_it(): void
    {
        Storage::fake('public');
        [$business] = $this->business();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/businesses/{$business->id}/reviews", [
                'rating' => 5,
                'comment' => 'With photo',
                'photo' => UploadedFile::fake()->image('review.jpg'),
            ])
            ->assertOk()
            ->assertJsonPath('review.rating', 5);

        $review = Review::firstOrFail();
        $this->assertNotNull($review->photo);
        $this->assertSame('approved', $review->status);
    }

    public function test_vendor_reviews_page_lists_moderation_status_and_actions(): void
    {
        [$business] = $this->business();
        $pending = Review::create([
            'user_id' => User::factory()->create()->id,
            'business_id' => $business->id,
            'rating' => 4,
            'comment' => 'Please approve me',
            'status' => 'pending',
        ]);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->get(route('admin.reviews', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Please approve me')
            ->assertSee('Pending');

        $this->actingAs($admin)
            ->put(route('admin.reviews.moderate', $pending->id), ['status' => 'approved'])
            ->assertRedirect();

        $this->assertSame('approved', $pending->fresh()->status);
    }

    private function business(): array
    {
        $category = Category::create(['name' => 'Cafe', 'slug' => 'cafe-'.uniqid(), 'module_type' => 'directory']);
        $business = Business::create([
            'category_id' => $category->id,
            'name' => 'Test Cafe',
            'slug' => 'test-cafe-'.uniqid(),
            'address' => 'Test address',
            'is_active' => true,
        ]);

        return [$business];
    }
}
