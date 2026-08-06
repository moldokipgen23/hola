<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\MediaLibrary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_gallery_page_lists_photos_and_counts(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        [$biz] = $this->fixture();

        $this->createPhoto($biz, 'exterior.jpg', 'approved');

        $this->actingAs($admin)
            ->get(route('admin.gallery'))
            ->assertOk()
            ->assertSee('exterior.jpg')
            ->assertSee('All (2)')
            ->assertSee('Approved (2)');
    }

    public function test_admin_can_moderate_photo_status(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        [$biz, $photo] = $this->fixture();

        $this->actingAs($admin)
            ->put(route('admin.gallery.moderate', $photo->id), [
                'status' => 'hidden',
                'reason' => 'Inappropriate',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $photo->refresh();
        $this->assertSame('hidden', $photo->status);
        $this->assertSame('Inappropriate', $photo->moderation_reason);
    }

    public function test_admin_can_update_caption_order_and_cover(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        [$biz] = $this->fixture();

        $a = $this->createPhoto($biz, 'a.jpg', 'approved', 0, true);
        $b = $this->createPhoto($biz, 'b.jpg', 'approved', 5, false);

        // Making B the cover should unset A's cover flag.
        $this->actingAs($admin)
            ->put(route('admin.gallery.detail', $b->id), [
                'alt_text' => 'New caption',
                'sort_order' => 2,
                'is_cover' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('New caption', $b->fresh()->alt_text);
        $this->assertSame(2, $b->fresh()->sort_order);
        $this->assertTrue((bool) $b->fresh()->is_cover);
        $this->assertFalse((bool) $a->fresh()->is_cover);
    }

    public function test_admin_can_delete_photo(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        [$biz, $photo] = $this->fixture();

        $this->actingAs($admin)
            ->delete(route('admin.gallery.destroy', $photo->id))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('media_library', ['id' => $photo->id]);
    }

    private function fixture(): array
    {
        $cat = Category::create(['name' => 'Restaurant', 'slug' => 'restaurant-gallery-'.uniqid(), 'module_type' => 'directory']);
        $biz = Business::create([
            'name' => 'Grand Cafe',
            'slug' => 'grand-cafe-'.uniqid(),
            'category_id' => $cat->id,
            'address' => 'x',
            'enabled_modules' => ['catalog' => true],
        ]);

        return [$biz, $this->createPhoto($biz, 'default.jpg')];
    }

    private function createPhoto(Business $biz, string $filename, string $status = 'approved', int $sort = 0, bool $cover = false): MediaLibrary
    {
        return MediaLibrary::create([
            'business_id' => $biz->id,
            'filename' => $filename,
            'original_filename' => $filename,
            'mime_type' => 'image/jpeg',
            'size_bytes' => 1024,
            'path' => 'public/'.$filename,
            'disk' => 'public',
            'category' => 'general',
            'sort_order' => $sort,
            'is_cover' => $cover,
            'status' => $status,
        ]);
    }
}
