<?php

namespace App\Http\Controllers\Integration;

use App\Models\Business;
use App\Services\IntegrationAuditService;
use App\Services\IntegrationWebhookService;
use Illuminate\Http\Request;

class LeadController extends BaseController
{
    public function index(Request $request)
    {
        $leads = Business::where('claim_status', 'unclaimed')
            ->with('category:id,name')
            ->when($request->search, fn ($q, $v) => $q->where('name', 'like', "%{$v}%"))
            ->when($request->category_id, fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->city, fn ($q, $v) => $q->where('city', $v))
            ->when($request->status, fn ($q, $v) => $q->where('claim_status', $v))
            ->orderBy($request->sort ?? 'created_at', $request->order ?? 'desc')
            ->paginate($request->per_page ?? 20);

        return $this->ok($leads);
    }

    public function show($id)
    {
        return $this->ok(Business::with('category:id,name')->findOrFail($id));
    }

    public function updateLeadScore(Request $request, $id)
    {
        $business = Business::findOrFail($id);

        $validated = $request->validate([
            'confidence' => 'sometimes|integer|min:0|max:100',
            'is_featured' => 'sometimes|boolean',
        ]);

        $business->update($validated);

        IntegrationAuditService::log($request, 'lead.scored', 'lead', (string) $business->id, $validated);

        app(IntegrationWebhookService::class)->dispatchForTenant(
            $request->input('integration_tenant_type', 'hola'),
            $request->input('integration_tenant_id'),
            'lead.updated',
            $business->fresh()->toArray()
        );

        return $this->ok($business->fresh());
    }
}
