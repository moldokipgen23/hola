<?php

namespace Tests\Unit;

use App\Models\Business;
use App\Models\Product;
use PHPUnit\Framework\TestCase;

class BusinessModuleContractTest extends TestCase
{
    public function test_product_accepts_inventory_fields_used_by_owner_api(): void
    {
        $product = new Product;
        $product->fill([
            'availability' => 'limited',
            'stock' => 7,
            'is_active' => false,
        ]);

        $this->assertSame('limited', $product->availability);
        $this->assertSame(7, $product->stock);
        $this->assertFalse($product->is_active);
    }

    public function test_business_module_helpers_use_enabled_module_configuration(): void
    {
        $business = new Business;
        $business->enabled_modules = [
            'bookings' => true,
            'orders' => false,
            'transport' => true,
            'turf' => false,
        ];

        $this->assertTrue($business->hasBookingsModule());
        $this->assertFalse($business->hasOrdersModule());
        $this->assertTrue($business->hasTransportModule());
        $this->assertFalse($business->hasTurfModule());
    }
}
