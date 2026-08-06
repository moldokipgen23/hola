<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $businessIds = Business::where('created_by', $user->id)->pluck('id');

        $query = MediaLibrary::where('user_id', $user->id)
            ->orWhereIn('business_id', $businessIds);

        if ($category = request('category')) {
            $query->where('category', $category);
        }

        $media = $query->latest()->paginate(40);

        return view('vendor.media.index', compact('media'));
    }

    public function upload(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'file' => 'required|file|max:10240',
            'business_id' => 'nullable|exists:businesses,id',
            'alt_text' => 'nullable|string|max:255',
            'category' => 'required|in:general,product,banner,avatar',
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('public', $filename);

        MediaLibrary::create([
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

        return back()->with('success', 'File uploaded.');
    }

    public function destroy(MediaLibrary $media)
    {
        $user = Auth::user();
        abort_unless($media->user_id === $user->id, 403);

        if ($media->path && Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $media->delete();

        return back()->with('success', 'Media deleted.');
    }
}
