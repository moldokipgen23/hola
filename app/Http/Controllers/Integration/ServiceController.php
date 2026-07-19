<?php

namespace App\Http\Controllers\Integration;

use App\Models\Service;
use App\Services\IntegrationAuditService;
use App\Services\IntegrationWebhookService;
use Illuminate\Http\Request;

class ServiceController extends BaseController
{
    public function index(Request $request)
    {
        $services = Service::with('business:id,name')
            ->when($request->business_id, fn ($q, $v) => $q->where('business_id', $v))
            ->when($request->search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy($request->sort ?? 'sort_order', $request->order ?? 'asc')
            ->paginate($request->per_page ?? 50);

        return $this->ok($services);
    }

    public function show($id)
    {
        return $this->ok(Service::with('business:id,name')->findOrFail($id));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_id' => 'required|integer|exists:businesses,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:15',
            'capacity' => 'nullable|integer|min:1',
            'category' => 'nullable|string|max:100',
            'image' => 'nullable|url|max:2048',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $service = Service::create($validated);

        IntegrationAuditService::log($request, 'service.created', 'service', (string) $service->id, $validated);

        app(IntegrationWebhookService::class)->dispatchForTenant(
            $request->input('integration_tenant_type', 'hola'),
            $request->input('integration_tenant_id'),
            'service.created',
            $service->toArray()
        );

        return $this->created($service);
    }

    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'duration' => 'sometimes|integer|min:15',
            'capacity' => 'nullable|integer|min:1',
            'category' => 'nullable|string|max:100',
            'image' => 'nullable|url|max:2048',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $service->update($validated);

        IntegrationAuditService::log($request, 'service.updated', 'service', (string) $service->id, $validated);

        app(IntegrationWebhookService::class)->dispatchForTenant(
            $request->input('integration_tenant_type', 'hola'),
            $request->input('integration_tenant_id'),
            'service.updated',
            $service->fresh()->toArray()
        );

        return $this->ok($service->fresh());
    }

    public function destroy(Request $request, $id)
    {
        $service = Service::findOrFail($id);
        $service->delete();

        IntegrationAuditService::log($request, 'service.deleted', 'service', (string) $id);

        app(IntegrationWebhookService::class)->dispatchForTenant(
            $request->input('integration_tenant_type', 'hola'),
            $request->input('integration_tenant_id'),
            'service.deleted',
            ['id' => (int) $id]
        );

        return $this->noContent();
    }
}
