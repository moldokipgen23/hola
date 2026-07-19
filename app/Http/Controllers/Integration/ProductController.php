<?php

namespace App\Http\Controllers\Integration;

use App\Models\Product;
use App\Services\IntegrationAuditService;
use App\Services\IntegrationWebhookService;
use Illuminate\Http\Request;

class ProductController extends BaseController
{
    public function index(Request $request)
    {
        $products = Product::with('business:id,name')
            ->when($request->business_id, fn ($q, $v) => $q->where('business_id', $v))
            ->when($request->search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($request->category, fn ($q, $v) => $q->where('category', $v))
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy($request->sort ?? 'created_at', $request->order ?? 'desc')
            ->paginate($request->per_page ?? 50);

        return $this->ok($products);
    }

    public function show($id)
    {
        return $this->ok(Product::with('business:id,name')->findOrFail($id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|integer|exists:businesses,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'image' => 'nullable|url|max:2048',
            'is_active' => 'nullable|boolean',
        ]);

        $product = Product::create($validated);

        IntegrationAuditService::log($request, 'product.created', 'product', (string) $product->id, $validated);

        app(IntegrationWebhookService::class)->dispatchForTenant(
            $request->input('integration_tenant_type', 'hola'),
            $request->input('integration_tenant_id'),
            'product.created',
            $product->toArray()
        );

        return $this->created($product);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'image' => 'nullable|url|max:2048',
            'is_active' => 'nullable|boolean',
        ]);

        $product->update($validated);

        IntegrationAuditService::log($request, 'product.updated', 'product', (string) $product->id, $validated);

        app(IntegrationWebhookService::class)->dispatchForTenant(
            $request->input('integration_tenant_type', 'hola'),
            $request->input('integration_tenant_id'),
            'product.updated',
            $product->fresh()->toArray()
        );

        return $this->ok($product->fresh());
    }

    public function destroy(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        IntegrationAuditService::log($request, 'product.deleted', 'product', (string) $id);

        app(IntegrationWebhookService::class)->dispatchForTenant(
            $request->input('integration_tenant_type', 'hola'),
            $request->input('integration_tenant_id'),
            'product.deleted',
            ['id' => (int) $id]
        );

        return $this->noContent();
    }
}
