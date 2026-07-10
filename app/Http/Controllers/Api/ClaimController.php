<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClaimRequest;
use App\Models\Business;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClaimController extends Controller
{
    public function index()
    {
        $claims = ClaimRequest::with(['user', 'business'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['claims' => $claims]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'message' => 'nullable|string|max:1000',
            'proof_document' => 'nullable|file|max:5120',
        ]);

        $existing = ClaimRequest::where('user_id', $request->user()->id)
            ->where('business_id', $request->business_id)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'You already have a pending claim for this business.'], 422);
        }

        $data = [
            'user_id' => $request->user()->id,
            'business_id' => $request->business_id,
            'message' => $request->message,
        ];

        if ($request->hasFile('proof_document')) {
            $data['proof_document'] = $request->file('proof_document')->store('claims', 'public');
        }

        $claim = ClaimRequest::create($data);

        NotificationService::claimSubmitted($claim);
        ActivityLogService::log('claim_submitted', $claim, ['business_id' => $claim->business_id]);

        return response()->json(['claim' => $claim, 'message' => 'Claim request submitted.'], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_notes' => 'nullable|string',
        ]);

        $claim = ClaimRequest::with(['business', 'user'])->findOrFail($id);
        $claim->update([
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
        ]);

        if ($request->status === 'approved') {
            $claim->business->update([
                'claim_status' => 'claimed',
                'created_by' => $claim->user_id,
            ]);
            if ($claim->user->role === 'customer') {
                $claim->user->update(['role' => 'owner']);
            }
            NotificationService::claimApproved($claim);
            ActivityLogService::log('claim_approved', $claim, ['business_id' => $claim->business_id, 'user_id' => $claim->user_id]);
        } else {
            NotificationService::claimRejected($claim);
            ActivityLogService::log('claim_rejected', $claim, ['business_id' => $claim->business_id]);
        }

        return response()->json(['claim' => $claim]);
    }

    public function myClaims(Request $request)
    {
        $claims = ClaimRequest::where('user_id', $request->user()->id)
            ->with('business')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['claims' => $claims]);
    }
}
