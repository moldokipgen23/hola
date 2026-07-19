<?php

namespace App\Http\Controllers\Integration;

use App\Models\Business;
use App\Services\IntegrationAuditService;
use Illuminate\Http\Request;

class BusinessController extends BaseController
{
    public function index(Request $request)
    {
        $businesses = Business::with('category:id,name')
            ->when($request->search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->is_verified !== null, fn ($q) => $q->where('is_verified', $request->boolean('is_verified')))
            ->when($request->city, fn ($q, $v) => $q->where('city', $v))
            ->orderBy($request->sort ?? 'created_at', $request->order ?? 'desc')
            ->paginate($request->per_page ?? 20);

        return $this->ok($businesses);
    }

    public function show($id)
    {
        $business = Business::with([
            'category:id,name',
            'products',
            'services',
            'reviews',
        ])->findOrFail($id);

        return $this->ok($business);
    }

    public function update(Request $request, $id)
    {
        $business = Business::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:10',
            'phone' => 'nullable|string|max:20',
            'whatsapp' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'is_active' => 'sometimes|boolean',
            'is_verified' => 'sometimes|boolean',
            'enabled_modules' => 'sometimes|array',
        ]);

        $business->update($validated);

        IntegrationAuditService::log($request, 'business.updated', 'business', (string) $business->id, $validated);

        return $this->ok($business->fresh()->load('category:id,name'));
    }
}
