<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(private readonly DeliveryEligibilityService $deliveryEligibility) {}

    /**
     * Resolve the current cart for a business. Identity is the signed-in user,
     * else a client-supplied guest token. Only one cart per (business, identity).
     */
    public function getOrCreate(Business $business, ?int $userId, ?string $guestToken): Cart
    {
        if (! $userId && ! $guestToken) {
            throw ValidationException::withMessages(['guest_token' => 'A guest token is required to manage a cart without an account.']);
        }

        $query = Cart::where('business_id', $business->id);
        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('guest_token', $guestToken);
        }

        $cart = $query->latest()->first();

        if ($cart) {
            if ($cart->expires_at && $cart->expires_at->lt(now())) {
                $cart->items()->delete();
                $cart->forceDelete();
                $cart = null;
            } else {
                return $cart;
            }
        }

        return Cart::create([
            'business_id' => $business->id,
            'user_id' => $userId,
            'guest_token' => $userId ? null : $guestToken,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function addItem(Cart $cart, int $productId, int $quantity = 1): Cart
    {
        $product = Product::where('business_id', $cart->business_id)->whereKey($productId)->lockForUpdate()->first();

        if (! $product) {
            throw ValidationException::withMessages(['product_id' => 'This product does not belong to this business.']);
        }
        $this->assertOrderable($product);

        $item = $cart->items()->where('product_id', $product->id)->first();
        $newQuantity = ($item?->quantity ?? 0) + $quantity;

        $this->assertEnoughStock($product, $newQuantity);

        if ($item) {
            $item->update(['quantity' => $newQuantity]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_price' => $product->price,
            ]);
        }

        return $cart->fresh();
    }

    public function updateQuantity(Cart $cart, int $productId, int $quantity): Cart
    {
        if ($quantity < 1 || $quantity > 100) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be between 1 and 100.']);
        }

        $item = $cart->items()->where('product_id', $productId)->first();
        if (! $item) {
            throw ValidationException::withMessages(['product_id' => 'This item is not in your cart.']);
        }

        $product = Product::where('business_id', $cart->business_id)->whereKey($productId)->lockForUpdate()->first();
        if ($product) {
            $this->assertOrderable($product);
            $this->assertEnoughStock($product, $quantity);
        }

        $item->update(['quantity' => $quantity]);

        return $cart->fresh();
    }

    public function removeItem(Cart $cart, int $productId): Cart
    {
        $cart->items()->where('product_id', $productId)->delete();

        return $cart->fresh();
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    /**
     * Server-authoritative summary: prices always come from the live product
     * row, never from client input. Includes delivery eligibility + min-order.
     */
    public function summary(Cart $cart, array $fulfilment = []): array
    {
        $cart->load(['items.product']);

        $rawSubtotal = 0.0;
        $lineItems = $cart->items->map(function (CartItem $item) use (&$rawSubtotal) {
            $product = $item->product;
            $available = $product ? $product->isCurrentlyOrderable() : false;

            if (! $product) {
                return [
                    'product_id' => $item->product_id,
                    'name' => 'Unavailable product',
                    'quantity' => $item->quantity,
                    'unit_price' => '0.00',
                    'line_total' => '0.00',
                    'available' => false,
                    'message' => 'This product is no longer available.',
                ];
            }

            $unitPrice = (float) $product->price ?? 0;
            $lineTotal = $unitPrice * $item->quantity;
            $rawSubtotal += $lineTotal;

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'image' => $product->image,
                'quantity' => $item->quantity,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'line_total' => number_format($lineTotal, 2, '.', ''),
                'available' => $available,
                'message' => $available ? null : $product->availability_message,
                'stock' => $product->stock,
            ];
        })->values();

        $subtotal = $rawSubtotal;

        $eligibility = null;
        $deliveryFee = 0.0;
        if (($fulfilment['delivery_method'] ?? 'delivery') === 'delivery') {
            $eligibility = $this->deliveryEligibility->check(
                $cart->business,
                $fulfilment['pincode'] ?? null,
                isset($fulfilment['latitude']) ? (float) $fulfilment['latitude'] : null,
                isset($fulfilment['longitude']) ? (float) $fulfilment['longitude'] : null,
                $fulfilment['area_id'] ?? null,
                $subtotal,
            );
            $deliveryFee = (float) ($eligibility['delivery_fee'] ?? 0);
        }

        $total = $subtotal + $deliveryFee;

        return [
            'cart_id' => $cart->id,
            'business' => $cart->business?->only(['id', 'name', 'slug', 'phone', 'whatsapp']),
            'items' => $lineItems,
            'item_count' => $cart->items->sum('quantity'),
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'delivery_fee' => number_format($deliveryFee, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
            'delivery' => $eligibility,
            'checkout_ready' => $cart->items->isNotEmpty()
                && $cart->items->every(fn (CartItem $item) => $item->product?->isCurrentlyOrderable())
                && (! $eligibility || $eligibility['deliverable']),
        ];
    }

    private function assertOrderable(Product $product): void
    {
        if (! $product->isCurrentlyOrderable()) {
            throw ValidationException::withMessages([
                'product_id' => "{$product->name}: {$product->availability_message}.",
            ]);
        }
    }

    private function assertEnoughStock(Product $product, int $quantity): void
    {
        if ($product->stock !== null && $product->stock < $quantity) {
            throw ValidationException::withMessages([
                'product_id' => "Insufficient stock for {$product->name}. Available: {$product->stock}",
            ]);
        }
    }
}
