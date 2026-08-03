<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\CapabilityTemplate;
use Illuminate\Http\Request;

class CapabilityTemplateController extends Controller
{
    public function index()
    {
        $templates = CapabilityTemplate::withCount('categories')->orderBy('sort_order')->get();

        return view('admin.capability-templates.index', compact('templates'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'enabled_modules' => 'nullable|array',
            'enabled_modules.*' => 'string',
            'enabled_experiences' => 'nullable|array',
            'enabled_experiences.*' => 'string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        }
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['enabled_modules'] = array_fill_keys($validated['enabled_modules'] ?? [], true);
        $validated['enabled_experiences'] = array_values($validated['enabled_experiences'] ?? []);

        CapabilityTemplate::create($validated);

        return redirect()->route('admin.capability-templates')->with('success', 'Template created.');
    }

    public function update(Request $request, CapabilityTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'enabled_modules' => 'nullable|array',
            'enabled_modules.*' => 'string',
            'enabled_experiences' => 'nullable|array',
            'enabled_experiences.*' => 'string',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
        }
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['enabled_modules'] = array_fill_keys($validated['enabled_modules'] ?? [], true);
        $validated['enabled_experiences'] = array_values($validated['enabled_experiences'] ?? []);

        $template->update($validated);

        return redirect()->route('admin.capability-templates')->with('success', 'Template updated.');
    }

    public function destroy(CapabilityTemplate $template)
    {
        $template->delete();

        return redirect()->route('admin.capability-templates')->with('success', 'Template deleted.');
    }

    public function assign(Request $request, CapabilityTemplate $template)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
        ]);

        $business = Business::findOrFail($validated['business_id']);
        $template->applyTo($business);

        return back()->with('success', "Template \"{$template->name}\" applied to {$business->name}.");
    }

    public function revoke(Request $request, CapabilityTemplate $template)
    {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
        ]);

        $business = Business::findOrFail($validated['business_id']);

        $moduleService = app(\App\Services\BusinessModuleService::class);
        $current = $moduleService->effectiveFor($business);
        foreach ($template->enabled_modules ?? [] as $module => $enabled) {
            if ($enabled && isset($current[$module])) {
                $current[$module] = false;
            }
        }
        $moduleService->update($business, $current);

        return back()->with('success', "Template \"{$template->name}\" revoked from {$business->name}.");
    }
}
