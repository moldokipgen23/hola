<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Category;
use App\Models\City;
use App\Models\Order;
use App\Models\Pincode;
use App\Models\Product;
use App\Services\PlaceLinkImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterPlanBackendTest extends TestCase
{
    use RefreshDatabase;

    public function test_place_link_extracts_google_place_id(): void
    {
        $importer = app(PlaceLinkImporter::class);

        $this->assertSame('ChIJN1t_tDeuEmsRUsoyG83frY4',
            $importer->extractPlaceId('https://www.google.com/maps/place/Hotel/@24.0,93.0,17z/data=!3m3!1sChIJN1t_tDeuEmsRUsoyG83frY4'));
        $this->assertSame('ChIJN1t_tDeuEmsRUsoyG83frY4',
            $importer->extractPlaceId('https://maps.google.com/?q=ChIJN1t_tDeuEmsRUsoyG83frY4'));
        $this->assertNull($importer->extractPlaceId('https://example.com/not-a-maps-link'));
    }

    public function test_per_business_order_numbers_are_sequential(): void
    {
        $business = $this->shoppingBusiness();
        $orders = collect(range(1, 3))->map(fn () => $this->placeOrder($business));

        $numbers = $orders->map(fn ($order) => $order->order_number)->values();
        $this->assertSame('ORD-'.$business->id.'-0001', $numbers[0]);
        $this->assertSame('ORD-'.$business->id.'-0002', $numbers[1]);
        $this->assertSame('ORD-'.$business->id.'-0003', $numbers[2]);
    }

    public function test_city_filter_returns_only_matching_businesses(): void
    {
        $lamka = City::create(['name' => 'Lamka', 'slug' => 'lamka', 'state' => 'Manipur', 'is_home' => true]);
        $delhi = City::create(['name' => 'Delhi', 'slug' => 'delhi', 'state' => 'Delhi']);
        $bizA = $this->directoryBusiness('Lamka Biz');
        $bizA->update(['city_id' => $lamka->id]);
        $bizB = $this->directoryBusiness('Delhi Biz');
        $bizB->update(['city_id' => $delhi->id]);

        $this->getJson('/api/businesses?city_id='.$delhi->id)
            ->assertOk()
            ->assertJsonCount(1, 'businesses.data')
            ->assertJsonPath('businesses.data.0.name', 'Delhi Biz');
    }

    private function shoppingBusiness(): Business
    {
        Pincode::updateOrCreate(['pincode' => '795128'], [
            'locality' => 'T', 'district' => 'Churachandpur', 'state' => 'Manipur', 'serviceable' => true,
        ]);
        $cat = Category::updateOrCreate(['slug' => 'food-restaurants'], [
            'name' => 'Food & Restaurants', 'module_type' => 'ordering', 'is_active' => true,
        ]);
        $suffix = str()->lower(str()->random(6));

        return Business::create([
            'category_id' => $cat->id,
            'name' => 'Shop '.$suffix, 'slug' => 'shop-'.$suffix,
            'address' => 'x', 'district' => 'Churachandpur', 'state' => 'Manipur',
            'pincode' => '795128', 'phone' => '9000000001', 'is_active' => true,
            'enabled_modules' => ['catalog' => true, 'orders' => true, 'inventory' => true],
        ]);
    }

    private function directoryBusiness(string $name): Business
    {
        Pincode::updateOrCreate(['pincode' => '795128'], [
            'locality' => 'T', 'district' => 'Churachandpur', 'state' => 'Manipur', 'serviceable' => true,
        ]);
        $cat = Category::updateOrCreate(['slug' => 'hotels-lodges'], [
            'name' => 'Hotels & Lodges', 'module_type' => 'booking', 'is_active' => true,
        ]);
        $slug = str()->slug($name).'-'.str()->lower(str()->random(5));

        return Business::create([
            'category_id' => $cat->id,
            'name' => $name, 'slug' => $slug,
            'address' => 'x', 'district' => 'Churachandpur', 'state' => 'Manipur',
            'pincode' => '795128', 'phone' => '9000000001', 'is_active' => true,
        ]);
    }

    private function placeOrder(Business $business): Order
    {
        $product = Product::create([
            'business_id' => $business->id, 'name' => 'Item', 'slug' => 'item-'.str()->lower(str()->random(6)),
            'price' => 100, 'is_active' => true, 'stock' => 10,
        ]);

        $response = $this->postJson('/api/businesses/'.$business->slug.'/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'customer_name' => 'Buyer', 'customer_phone' => '9000000002',
            'delivery_method' => 'pickup',
            'client_reference' => 'ref-'.str()->lower(str()->random(8)),
        ]);

        return Order::findOrFail($response->json('order.id'));
    }
}
