<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cartService) {}

    private function resolveCart(Request $request, string $slug, bool $create = false)
    {
        $business = Business::active()->inServiceableArea()->where('slug', $slug)->firstOrFail();

        if (! $business->hasOrdersModule()) {
            return [null, response()->json(['message' => 'Orders not available for this business.'], 422)];
        }

        $guestToken = $request->header('X-Guest-Token') ?? $request->input('guest_token');
        $userId = $request->user()?->id;

        $cart = $this->cartService->getOrCreate($business, $userId, $guestToken);

        return [$cart, null];
    }

    public function show(Request $request, $slug)
    {
        [$cart, $error] = $this->resolveCart($request, $slug);
        if ($error) {
            return $error;
        }

        return response()->json(['cart' => $this->cartService->summary($cart, $request->only(['delivery_method', 'pincode', 'latitude', 'longitude', 'area_id']))]);
    }

    public function add(Request $request, $slug)
    {
        [$cart, $error] = $this->resolveCart($request, $slug, true);
        if ($error) {
            return $error;
        }

        $validated = $request->validate([
            'product_id' => 'required|integer',
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $cart = $this->cartService->addItem($cart, (int) $validated['product_id'], (int) $validated['quantity']);

        return response()->json([
            'message' => 'Added to cart.',
            'cart' => $this->cartService->summary($cart, $request->only(['delivery_method', 'pincode', 'latitude', 'longitude', 'area_id'])),
        ], 201);
    }

    public function update(Request $request, $slug, $productId)
    {
        [$cart, $error] = $this->resolveCart($request, $slug);
        if ($error) {
            return $error;
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:100',
        ]);

        $cart = $this->cartService->updateQuantity($cart, (int) $productId, (int) $validated['quantity']);

        return response()->json([
            'message' => 'Cart updated.',
            'cart' => $this->cartService->summary($cart, $request->only(['delivery_method', 'pincode', 'latitude', 'longitude', 'area_id'])),
        ]);
    }

    public function remove(Request $request, $slug, $productId)
    {
        [$cart, $error] = $this->resolveCart($request, $slug);
        if ($error) {
            return $error;
        }

        $cart = $this->cartService->removeItem($cart, (int) $productId);

        return response()->json([
            'message' => 'Item removed from cart.',
            'cart' => $this->cartService->summary($cart, $request->only(['delivery_method', 'pincode', 'latitude', 'longitude', 'area_id'])),
        ]);
    }
}
