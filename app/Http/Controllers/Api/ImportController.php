<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImportController extends Controller
{
    public function index()
    {
        $batches = ImportBatch::withCount('items')
            ->with('agent:id,name,avatar')
            ->latest()
            ->paginate(20);

        return response()->json(['batches' => $batches]);
    }

    public function review(Request $request)
    {
        $query = ImportItem::where('status', 'pending')
            ->with('batch:id,name,source');

        if ($request->batch_id) {
            $query->where('batch_id', $request->batch_id);
        }

        if ($request->source) {
            $query->whereHas('batch', fn($q) => $q->where('source', $request->source));
        }

        $items = $query->latest()->paginate(20);

        return response()->json(['items' => $items]);
    }

    public function approve($id)
    {
        $item = ImportItem::with('batch')->findOrFail($id);

        $data = $item->data;
        $categories = Category::pluck('id', 'name')->toArray();

        // Auto-match category
        $categoryId = null;
        if (!empty($data['category'])) {
            $categoryId = $categories[$data['category']] ?? null;
        }
        if (!$categoryId && !empty($data['types'])) {
            // Try to match from Google types
            foreach ($data['types'] as $type) {
                foreach ($categories as $name => $id) {
                    if (Str::contains(Str::lower($name), $type)) {
                        $categoryId = $id;
                        break 2;
                    }
                }
            }
        }

        $slug = Str::slug($data['name']);
        $existing = Business::where('slug', $slug)->first();
        if ($existing) {
            $slug .= '-' . Str::random(5);
        }

        $business = Business::create([
            'name' => $data['name'] ?? '',
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? '',
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'category_id' => $categoryId,
            'source' => $item->batch->source ?? 'import',
            'external_id' => $item->external_id,
            'import_batch_id' => $item->batch_id,
            'confidence' => $item->confidence,
            'is_active' => true,
        ]);

        $item->update([
            'status' => 'approved',
            'business_id' => $business->id,
        ]);

        // Update batch counts
        $item->batch->increment('approved');
        $item->batch->decrement('pending');

        return response()->json([
            'message' => 'Business created.',
            'business' => $business,
        ]);
    }

    public function reject($id)
    {
        $item = ImportItem::findOrFail($id);
        $item->update([
            'status' => 'rejected',
            'notes' => request('notes', 'Rejected by admin'),
        ]);

        $item->batch->increment('rejected');
        $item->batch->decrement('pending');

        return response()->json(['message' => 'Item rejected.']);
    }

    public function approveAll(Request $request)
    {
        $batchId = $request->batch_id;
        $query = ImportItem::where('status', 'pending');

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $items = $query->get();
        $approved = 0;

        foreach ($items as $item) {
            try {
                $this->approve($item->id);
                $approved++;
            } catch (\Exception $e) {
                continue;
            }
        }

        return response()->json([
            'message' => "{$approved} businesses approved.",
            'approved' => $approved,
        ]);
    }

    public function destroy($id)
    {
        $item = ImportItem::findOrFail($id);
        $item->delete();

        return response()->json(['message' => 'Item deleted.']);
    }
}
