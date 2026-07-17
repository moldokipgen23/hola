<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Razorpay\Api\Api;

class PaymentController extends Controller
{
    private function razorpay(): Api
    {
        return new Api(config('services.razorpay.key_id'), config('services.razorpay.key_secret'));
    }

    public function createOrder(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'nullable|string|size:3',
            'type' => 'required|in:order,booking,trip',
            'reference_id' => 'required|integer',
            'gateway' => 'nullable|in:razorpay,cashfree',
        ]);

        $gateway = $validated['gateway'] ?? 'razorpay';
        $currency = $validated['currency'] ?? 'INR';
        $amountPaise = (int) round($validated['amount'] * 100);
        $receipt = $validated['type'] . '_' . $validated['reference_id'] . '_' . time();

        if ($gateway === 'cashfree') {
            $env = config('services.cashfree.env', 'TEST');
            $baseUrl = $env === 'PRODUCTION'
                ? 'https://api.cashfree.com/pg'
                : 'https://sandbox.cashfree.com/pg';

            $orderId = 'CF_' . $receipt;

            $customerName = 'Customer';
            $customerPhone = '9999999999';
            $customerEmail = 'customer@example.com';

            $model = match ($validated['type']) {
                'order' => Order::class,
                'booking' => Booking::class,
                'trip' => Trip::class,
            };
            $record = $model::find($validated['reference_id']);
            if ($record) {
                $customerName = $record->customer_name ?? 'Customer';
                $customerPhone = $record->customer_phone ?? '9999999999';
                $customerEmail = $record->customer_email ?? 'customer@example.com';
            }

            $response = Http::withHeaders([
                'x-api-version' => '2023-08-01',
                'x-client-id' => config('services.cashfree.app_id'),
                'x-client-secret' => config('services.cashfree.secret_key'),
                'Content-Type' => 'application/json',
            ])->post($baseUrl . '/orders', [
                'order_id' => $orderId,
                'order_amount' => (float) $validated['amount'],
                'order_currency' => $currency,
                'order_note' => $validated['type'] . ' #' . $validated['reference_id'],
                'customer_details' => [
                    'customer_id' => 'cust_' . ($request->user()?->id ?? 0),
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                ],
            ]);

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Cashfree order creation failed.',
                    'error' => $response->json(),
                ], 422);
            }

            $data = $response->json();

            return response()->json([
                'gateway' => 'cashfree',
                'payment_session_id' => $data['payment_session_id'] ?? null,
                'order_id' => $data['order_id'] ?? $orderId,
                'amount' => $amountPaise,
                'currency' => $currency,
                'app_id' => config('services.cashfree.app_id'),
                'env' => $env === 'PRODUCTION' ? 'PRODUCTION' : 'TEST',
            ]);
        }

        $razorpayOrder = $this->razorpay()->order->create([
            'receipt' => $receipt,
            'amount' => $amountPaise,
            'currency' => $currency,
        ]);

        return response()->json([
            'gateway' => 'razorpay',
            'id' => $razorpayOrder['id'],
            'amount' => $razorpayOrder['amount'],
            'currency' => $razorpayOrder['currency'],
            'key_id' => config('services.razorpay.key_id'),
        ]);
    }

    public function verifyPayment(Request $request)
    {
        $validated = $request->validate([
            'gateway' => 'nullable|in:razorpay,cashfree',
            'type' => 'required|in:order,booking,trip',
            'reference_id' => 'required|integer',
            'razorpay_order_id' => 'required_if:gateway,razorpay|string',
            'razorpay_payment_id' => 'required_if:gateway,razorpay|string',
            'razorpay_signature' => 'required_if:gateway,razorpay|string',
            'payment_id' => 'required_if:gateway,cashfree|string',
        ]);

        $gateway = $validated['gateway'] ?? 'razorpay';

        if ($gateway === 'razorpay') {
            try {
                $this->razorpay()->utility->verifyPaymentSignature([
                    'razorpay_order_id' => $validated['razorpay_order_id'],
                    'razorpay_payment_id' => $validated['razorpay_payment_id'],
                    'razorpay_signature' => $validated['razorpay_signature'],
                ]);
            } catch (\Exception $e) {
                return response()->json(['message' => 'Payment verification failed.'], 422);
            }
        }

        $model = match ($validated['type']) {
            'order' => Order::class,
            'booking' => Booking::class,
            'trip' => Trip::class,
        };

        $record = $model::findOrFail($validated['reference_id']);

        $update = ['payment_status' => 'paid'];
        if ($gateway === 'razorpay') {
            $update['razorpay_order_id'] = $validated['razorpay_order_id'];
            $update['razorpay_payment_id'] = $validated['razorpay_payment_id'];
            $update['razorpay_signature'] = $validated['razorpay_signature'];
        } else {
            $update['razorpay_order_id'] = $validated['payment_id'];
            $update['razorpay_payment_id'] = $validated['payment_id'];
        }
        $record->update($update);

        Transaction::create([
            'user_id' => $request->user()?->id ?? 0,
            'billable_type' => $model,
            'billable_id' => $record->id,
            'type' => $validated['type'],
            'amount' => $record->total ?? $record->fare ?? 0,
            'currency' => 'INR',
            'status' => 'completed',
            'payment_method' => $gateway,
            'payment_id' => $validated['razorpay_payment_id'] ?? $validated['payment_id'],
            'metadata' => $validated,
        ]);

        return response()->json([
            'message' => 'Payment verified successfully.',
            'payment_status' => 'paid',
        ]);
    }

    public function config(Request $request)
    {
        $razorpayEnabled = Setting::get('payment_razorpay_enabled', config('services.razorpay.key_id') ? true : false);
        $cashfreeEnabled = Setting::get('payment_cashfree_enabled', config('services.cashfree.app_id') ? true : false);
        $codEnabled = Setting::get('payment_cod_enabled', true);

        $config = [
            'gateways' => [],
            'default' => Setting::get('payment_default', 'cod'),
        ];

        if ($codEnabled) {
            $config['gateways'][] = 'cod';
        }

        if ($razorpayEnabled) {
            $config['gateways'][] = 'razorpay';
        }

        if ($cashfreeEnabled) {
            $config['gateways'][] = 'cashfree';
        }

        // Per-vendor payment methods
        $vendorMethods = null;
        if ($request->business_id) {
            $business = Business::find($request->business_id);
            if ($business && $business->payment_methods) {
                $vendorMethods = array_intersect($config['gateways'], $business->payment_methods);
            }
        }

        return response()->json([
            'razorpay' => [
                'key_id' => Setting::get('payment_razorpay_key_id', config('services.razorpay.key_id')),
                'enabled' => $razorpayEnabled,
            ],
            'cashfree' => [
                'app_id' => Setting::get('payment_cashfree_app_id', config('services.cashfree.app_id')),
                'env' => Setting::get('payment_cashfree_env', config('services.cashfree.env', 'TEST')),
                'enabled' => $cashfreeEnabled,
            ],
            'cod' => [
                'enabled' => $codEnabled,
            ],
            'config' => $config,
            'vendor_methods' => $vendorMethods,
        ]);
    }
}
