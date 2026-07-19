<?php

namespace App\Http\Controllers\Integration;

use App\Models\User;
use App\Services\IntegrationAuditService;
use Illuminate\Http\Request;

class CustomerController extends BaseController
{
    public function index(Request $request)
    {
        $customers = User::whereIn('role', ['customer', 'owner'])
            ->when($request->search, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('name', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%")->orWhere('phone', 'like', "%{$v}%");
            }))
            ->when($request->role, fn ($q, $v) => $q->where('role', $v))
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy($request->sort ?? 'created_at', $request->order ?? 'desc')
            ->paginate($request->per_page ?? 20);

        return $this->ok($customers);
    }

    public function show($id)
    {
        return $this->ok(User::withCount(['bookings', 'orders'])->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
        ]);

        $user->update($validated);

        IntegrationAuditService::log($request, 'customer.updated', 'customer', (string) $user->id, $validated);

        return $this->ok($user->fresh());
    }
}
