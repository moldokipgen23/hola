<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\Pincode;
use App\Models\Setting;
use App\Services\ImportMergeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        $query = ImportItem::inPipeline()
            ->with('batch:id,name,source');

        if ($request->batch_id) {
            $query->where('batch_id', $request->batch_id);
        }

        if ($request->source) {
            $query->whereHas('batch', fn ($q) => $q->where('source', $request->source));
        }

        $items = $query->latest()->paginate(20);

        return response()->json(['items' => $items]);
    }

    public function approve($id)
    {
        $item = ImportItem::with('batch')->findOrFail($id);
        $data = $item->data;

        // DUPLICATE CHECK — flag for merge (linked via duplicate_of) instead of rejecting.
        $existingBusiness = app(ImportMergeService::class)->findExistingDuplicate($item);

        if ($existingBusiness) {
            app(ImportMergeService::class)->flagDuplicate($item, $existingBusiness);

            return response()->json([
                'message' => "Duplicate: Business already exists ({$existingBusiness->name}). Flagged for merge.",
                'duplicate_of' => $existingBusiness->id,
                'skipped' => true,
            ]);
        }

        $taxonomy = resolveApprovedImportTaxonomy($data);
        if (! $taxonomy) {
            return response()->json([
                'message' => 'This import needs an approved Business Type mapping before it can be approved.',
            ], 422);
        }
        $categoryId = $taxonomy['category_id'];
        $subcategoryId = $taxonomy['subcategory_id'];

        $slug = Str::slug($data['name']);
        $existing = Business::where('slug', $slug)->first();
        if ($existing) {
            $slug .= '-'.Str::random(5);
        }

        // Download photos if available (never store provider URLs with API keys)
        $photos = [];
        if (! empty($data['photos']) && is_array($data['photos'])) {
            $apiKey = Setting::get('api_key_google_places') ?? config('services.google.places_api_key');
            foreach ($data['photos'] as $photoEntry) {
                $photoUrl = null;
                if (is_string($photoEntry)) {
                    $photoUrl = $photoEntry;
                } elseif (is_array($photoEntry) && ! empty($photoEntry['photo_reference']) && $apiKey) {
                    $photoUrl = "https://maps.googleapis.com/maps/api/place/photo?photoreference={$photoEntry['photo_reference']}&maxwidth=800&key={$apiKey}";
                }
                if (! $photoUrl) {
                    continue;
                }
                try {
                    $response = Http::timeout(10)->get($photoUrl);
                    if ($response->successful() && strlen($response->body()) > 100) {
                        $ext = 'jpg';
                        $filename = 'businesses/'.$slug.'_'.Str::random(6).'.'.$ext;
                        Storage::disk('public')->put($filename, $response->body());
                        $photos[] = 'storage/'.$filename;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        // Derive pincode from data or fallback
        $pincode = null;
        if (! empty($data['pincode'])) {
            $pincode = Pincode::lookup($data['pincode']);
        }
        // Try to find pincode by lat/lng fallback
        if (! $pincode && ! empty($data['latitude']) && ! empty($data['longitude'])) {
            $pincode = Pincode::haversine($data['latitude'], $data['longitude'], 1)->first();
        }
        // Last resort fallback to district default
        if (! $pincode) {
            $district = $data['district'] ?? 'Churachandpur';
            $pincode = Pincode::where('district', $district)->first();
        }

        $business = Business::create([
            'name' => $data['name'] ?? '',
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? '',
            'locality' => $data['locality'] ?? null,
            'district' => $data['district'] ?? $pincode?->district ?? 'Unknown',
            'pincode' => $pincode?->pincode,
            'state' => $pincode?->state ?? ($data['state'] ?? null),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'category_id' => $categoryId,
            'subcategory_id' => $subcategoryId,
            'source' => $item->batch->source ?? 'import',
            'external_id' => $item->external_id,
            'import_batch_id' => $item->batch_id,
            'confidence' => $item->confidence,
            'photos' => count($photos) > 0 ? $photos : null,
            'working_hours' => $data['working_hours'] ?? null,
            'average_rating' => $data['rating'] ?? 0,
            'review_count' => $data['total_ratings'] ?? 0,
            'is_active' => true,
        ]);

        // Single write-path: keep businesses.category_id and the classification
        // table in sync so imported businesses appear in the Directory world.
        $business->syncPrimaryClassification($categoryId, 'import_approved');

        $item->update([
            'status' => 'approved',
            'business_id' => $business->id,
        ]);

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

    public function merge($id)
    {
        $item = ImportItem::with('duplicateOf')->findOrFail($id);
        $existing = $item->duplicateOf;

        if (! $existing) {
            return response()->json([
                'message' => 'This item is not linked to an existing business. Approve it first to flag the duplicate.',
            ], 422);
        }

        $fieldsCopied = app(ImportMergeService::class)->merge($item);

        return response()->json([
            'message' => "Merged into {$existing->name}.",
            'duplicate_of' => $existing->id,
            'fields_copied' => $fieldsCopied,
            'status' => $item->status,
        ]);
    }

    public function approveAll(Request $request)
    {
        $batchId = $request->batch_id;
        $query = ImportItem::inPipeline();

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
