<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\World;
use App\Models\WorldHomepageContent;
use Illuminate\Http\Request;

class HomepageContentController extends Controller
{
    public function index()
    {
        $contents = WorldHomepageContent::with('world')
            ->orderBy('world_id')
            ->orderBy('sort_order')
            ->get();
        $worlds = World::active()->orderBy('name')->get();

        return view('admin.homepage.index', compact('contents', 'worlds'));
    }

    public function create()
    {
        $worlds = World::active()->orderBy('name')->get();
        $content = new WorldHomepageContent;

        return view('admin.homepage.create', compact('worlds', 'content'));
    }

    public function edit(WorldHomepageContent $content)
    {
        $worlds = World::active()->orderBy('name')->get();

        return view('admin.homepage.create', compact('worlds', 'content'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'world_id' => 'required|exists:worlds,id',
            'section_type' => 'required|in:hero,featured,categories,promotions',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'image_url' => 'nullable|url|max:500',
            'link_url' => 'nullable|url|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'meta' => 'nullable|json',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        if (! empty($validated['meta'])) {
            $validated['meta'] = json_decode($validated['meta'], true);
        }

        WorldHomepageContent::create($validated);

        return redirect()->route('admin.homepage')->with('success', 'Homepage section created.');
    }

    public function update(Request $request, WorldHomepageContent $content)
    {
        $validated = $request->validate([
            'world_id' => 'required|exists:worlds,id',
            'section_type' => 'required|in:hero,featured,categories,promotions',
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:500',
            'image_url' => 'nullable|url|max:500',
            'link_url' => 'nullable|url|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'meta' => 'nullable|json',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        if (! empty($validated['meta'])) {
            $validated['meta'] = json_decode($validated['meta'], true);
        }

        $content->update($validated);

        return redirect()->route('admin.homepage')->with('success', 'Homepage section updated.');
    }

    public function destroy(WorldHomepageContent $content)
    {
        $content->delete();

        return redirect()->route('admin.homepage')->with('success', 'Homepage section deleted.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*' => 'required|integer|exists:world_homepage_content,id',
        ]);

        foreach ($request->order as $index => $id) {
            WorldHomepageContent::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['message' => 'Order updated.']);
    }
}
