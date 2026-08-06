<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderPlacementService
{
    public function __construct(private readonly DeliveryEligibilityService $deliveryEligibility) {}

    public function place(
        Business $business,
        array $items,
        array $customer,
        array $fulfilment,
        ?int $userId = null,
        ?string $clientReference = null,
    ): array {
        try {
            return DB::transaction(function () use ($business, $clientReference, $customer, $fulfilment, $items, $userId) {
                if ($clientReference) {
                    $existing = Order::where('client_reference', $clientReference)->lockForUpdate()->first();
                    if ($existing) {
                        $this->assertReferenceBelongsToRequest($existing, $business, $customer, $userId);

                        return ['order' => $existing->load('items'), 'duplicate' => true];
                    }
                }

                $productIds = collect($items)->pluck('product_id')->unique();
                $lockedProducts = Product::where('business_id', $business->id)
                    ->whereIn('id', $productIds)
                    ->lockForUpdate()
                    ->get();

                if ($lockedProducts->count() !== $productIds->count()) {
                    throw ValidationException::withMessages(['items' => 'One or more products do not belong to this business.']);
                }

                $subtotal = 0.0;
                foreach ($items as $item) {
                    $product = $lockedProducts->find($item['product_id']);
                    if (! $product->isCurrentlyOrderable()) {
                        throw ValidationException::withMessages([
                            'items' => "{$product->name}: {$product->availability_message}.",
                        ]);
                    }
                    if ($product->price === null) {
                        throw ValidationException::withMessages(['items' => "Contact the business for the current price of {$product->name}."]);
                    }
                    if ($product->stock !== null && $product->stock < $item['quantity']) {
                        throw ValidationException::withMessages([
                            'items' => "Insufficient stock for {$product->name}. Available: {$product->stock}",
                        ]);
                    }

                    $subtotal += (float) $product->price * $item['quantity'];
                }

                $deliveryFee = 0.0;
                if ($fulfilment['delivery_method'] === 'delivery') {
                    $eligibility = $this->deliveryEligibility->check(
                        $business,
                        $fulfilment['pincode'] ?? null,
                        $fulfilment['latitude'] ?? null,
                        $fulfilment['longitude'] ?? null,
                        $fulfilment['area_id'] ?? null,
                        $subtotal,
                    );

                    if (! $eligibility['deliverable']) {
                        throw ValidationException::withMessages(['pincode' => $eligibility['message']]);
                    }

                    $deliveryFee = (float) ($eligibility['delivery_fee'] ?? 0);
                }

                $preparationMinutes = (int) $lockedProducts
                    ->filter(fn ($product) => $productIds->contains($product->id))
                    ->max('preparation_minutes');

                // Server-side tax + discount from the business settings.
                $taxAmount = round($subtotal * ((float) ($business->tax_percent ?? 0) / 100), 2);
                $discountAmount = (float) ($business->discount_amount ?? 0);

                // Per-business sequential order number (race-safe: the business
                // row is locked for update earlier in this transaction).
                $lastSequence = Order::where('business_id', $business->id)
                    ->where('order_number', 'like', 'ORD-'.$business->id.'-%')
                    ->count();
                $orderNumber = 'ORD-'.$business->id.'-'.str_pad((string) ($lastSequence + 1), 4, '0', STR_PAD_LEFT);

                $order = Order::create([
                    'business_id' => $business->id,
                    'user_id' => $userId,
                    'order_number' => $orderNumber,
                    'client_reference' => $clientReference,
                    'customer_name' => $customer['name'],
                    'customer_phone' => $customer['phone'],
                    'customer_email' => $customer['email'] ?? null,
                    'delivery_address' => $fulfilment['delivery_address'] ?? null,
                    'delivery_pincode' => $fulfilment['pincode'] ?? null,
                    'customer_latitude' => $fulfilment['latitude'] ?? null,
                    'customer_longitude' => $fulfilment['longitude'] ?? null,
                    'delivery_method' => $fulfilment['delivery_method'],
                    'delivery_time_slot' => $fulfilment['delivery_time_slot'] ?? null,
                    'estimated_ready_at' => $preparationMinutes > 0 ? now()->addMinutes($preparationMinutes) : null,
                    'notes' => $customer['notes'] ?? null,
                    'status' => 'pending',
                    'payment_status' => 'pending',
                    'payment_method' => 'cash',
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'tax' => $taxAmount,
                    'discount' => $discountAmount,
                    'total' => max(0, $subtotal + $taxAmount + $deliveryFee - $discountAmount),
                    'metadata' => ['payment_mode' => 'offline'],
                ]);

                foreach ($items as $item) {
                    $product = $lockedProducts->find($item['product_id']);
                    $stockTracked = $product->stock !== null;

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $product->id,
                        'name' => $product->name,
                        'description' => $product->description,
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->price,
                        'total_price' => (float) $product->price * $item['quantity'],
                        'metadata' => ['stock_tracked' => $stockTracked],
                    ]);

                    if ($stockTracked) {
                        $product->decrement('stock', $item['quantity']);
                    }
                }

                NotificationService::newOrder($order);

                return ['order' => $order->load('items'), 'duplicate' => false];
            });
        } catch (QueryException $exception) {
            // A simultaneous retry can reach the unique index before it sees the
            // first transaction. Return the committed order instead of charging
            // stock twice.
            $existing = $clientReference
                ? Order::where('client_reference', $clientReference)->first()
                : null;

            if (! $existing) {
                throw $exception;
            }

            $this->assertReferenceBelongsToRequest($existing, $business, $customer, $userId);

            return ['order' => $existing->load('items'), 'duplicate' => true];
        }
    }

    private function assertReferenceBelongsToRequest(
        Order $order,
        Business $business,
        array $customer,
        ?int $userId,
    ): void {
        $wrongUser = ($order->user_id !== null || $userId !== null)
            && $order->user_id !== $userId;

        if ($order->business_id !== $business->id
            || $wrongUser
            || $order->customer_phone !== $customer['phone']) {
            throw ValidationException::withMessages([
                'client_reference' => 'This order reference is already in use.',
            ]);
        }
    }
}
