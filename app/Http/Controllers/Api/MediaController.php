<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = MediaLibrary::where('user_id', $user->id);

        if ($businessId = $request->business_id) {
            $query->where('business_id', $businessId);
        }

        if ($category = $request->category) {
            $query->where('category', $category);
        }

        $media = $query->latest()->paginate(20);

        return response()->json(['data' => $media]);
    }

    public function upload(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:10240',
            'business_id' => 'nullable|exists:businesses,id',
            'alt_text' => 'nullable|string|max:255',
            'category' => 'required|in:general,product,banner,avatar',
        ]);

        if (! empty($validated['business_id']) && ! $user->isAdmin()) {
            Business::whereKey($validated['business_id'])
                ->where('created_by', $user->id)
                ->firstOrFail();
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('', $filename, 'public');

        $media = MediaLibrary::create([
            'user_id' => $user->id,
            'business_id' => $validated['business_id'] ?? null,
            'filename' => $filename,
            'original_filename' => $originalName,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'path' => $path,
            'disk' => 'public',
            'alt_text' => $validated['alt_text'] ?? null,
            'category' => $validated['category'],
        ]);

        return response()->json([
            'data' => $media,
            'url' => Storage::disk('public')->url($filename),
        ], 201);
    }

    public function destroy(MediaLibrary $media)
    {
        Gate::authorize('delete', $media);

        if ($media->path && Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $media->delete();

        return response()->json(['message' => 'Media deleted.']);
    }
}
