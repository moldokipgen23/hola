<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Business;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Product;
use App\Models\ClaimRequest;
use App\Models\Report;
use Illuminate\Support\Facades\Hash;

// Home redirect to admin
Route::get('/', fn () => redirect()->route('admin.dashboard'));

// Admin Login
Route::get('/admin/login', fn () => view('auth.login'))->name('admin.login');
Route::post('/admin/login', function (Request $request) {
    $request->validate(['email' => 'required|email', 'password' => 'required']);

    $user = User::where('email', $request->email)->whereIn('role', ['admin', 'super_admin', 'moderator'])->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return back()->withErrors(['email' => 'Invalid credentials.']);
    }

    Auth::login($user);
    $request->session()->regenerate();

    return redirect()->route('admin.dashboard');
})->name('admin.login.post');

Route::post('/admin/logout', function () {
    Auth::logout();
    return redirect()->route('admin.login');
})->name('admin.logout');

// Admin Routes (protected)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {
        $stats = [
            'businesses' => Business::count(),
            'active_businesses' => Business::where('is_active', true)->count(),
            'categories' => Category::count(),
            'users' => User::count(),
        ];
        $recentBusinesses = Business::latest()->take(5)->get();
        $pendingClaims = ClaimRequest::where('status', 'pending')->with('user', 'business')->take(5)->get();

        return view('admin.dashboard', compact('stats', 'recentBusinesses', 'pendingClaims'));
    })->name('dashboard');

    // Businesses
    Route::get('/businesses', function () {
        $query = Business::with('category');
        if ($search = request('search')) {
            $safe = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where('name', 'like', $safe);
        }
        $businesses = $query->latest()->paginate(20);
        return view('admin.businesses.index', compact('businesses'));
    })->name('businesses');

    Route::get('/businesses/create', function () {
        $categories = Category::orderBy('name')->get();
        $subcategories = Subcategory::orderBy('name')->get();
        return view('admin.businesses.form', compact('categories', 'subcategories'));
    })->name('businesses.create');

    Route::post('/businesses', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'category_id' => 'required|exists:categories,id',
            'address' => 'required|max:255',
            'description' => 'nullable',
            'phone' => 'nullable|max:20',
            'email' => 'nullable|email',
            'locality' => 'nullable|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $validated['slug'] = $request->slug ?: \Illuminate\Support\Str::slug($request->name);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['district'] = 'Churachandpur';

        Business::create($validated);

        return redirect()->route('admin.businesses')->with('success', 'Business created.');
    })->name('businesses.store');

    Route::get('/businesses/{id}', function ($id) {
        $business = Business::with(['category', 'subcategory', 'products', 'reviews.user', 'user', 'createdBy'])->findOrFail($id);
        return view('admin.businesses.show', compact('business'));
    })->name('businesses.show');

    Route::get('/businesses/{id}/edit', function ($id) {
        $business = Business::findOrFail($id);
        $categories = Category::orderBy('name')->get();
        $subcategories = Subcategory::orderBy('name')->get();
        return view('admin.businesses.form', compact('business', 'categories', 'subcategories'));
    })->name('businesses.edit');

    Route::put('/businesses/{id}', function (Request $request, $id) {
        $business = Business::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|max:255',
            'category_id' => 'required|exists:categories,id',
            'address' => 'required|max:255',
            'description' => 'nullable',
            'phone' => 'nullable|max:20',
            'email' => 'nullable|email',
            'locality' => 'nullable|max:100',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $validated['slug'] = $request->slug ?: \Illuminate\Support\Str::slug($request->name);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');

        $business->update($validated);

        return redirect()->route('admin.businesses')->with('success', 'Business updated.');
    })->name('businesses.update');

    Route::delete('/businesses/{id}', function ($id) {
        Business::findOrFail($id)->delete();
        return redirect()->route('admin.businesses')->with('success', 'Business deleted.');
    })->name('businesses.destroy');

    // Categories
    Route::get('/categories', function () {
        $categories = Category::withCount('businesses')->orderBy('name')->get();
        return view('admin.categories.index', compact('categories'));
    })->name('categories');

    Route::get('/categories/create', function () {
        return view('admin.categories.form');
    })->name('categories.create');

    Route::post('/categories', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'icon' => 'nullable|max:10',
        ]);

        $validated['slug'] = $request->slug ?: \Illuminate\Support\Str::slug($request->name);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['order'] = $request->order ?? 0;

        Category::create($validated);

        return redirect()->route('admin.categories')->with('success', 'Category created.');
    })->name('categories.store');

    Route::get('/categories/{id}/edit', function ($id) {
        $category = Category::findOrFail($id);
        return view('admin.categories.form', compact('category'));
    })->name('categories.edit');

    Route::put('/categories/{id}', function (Request $request, $id) {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|max:255',
            'icon' => 'nullable|max:10',
        ]);

        $validated['slug'] = $request->slug ?: \Illuminate\Support\Str::slug($request->name);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['order'] = $request->order ?? 0;

        $category->update($validated);

        return redirect()->route('admin.categories')->with('success', 'Category updated.');
    })->name('categories.update');

    Route::delete('/categories/{id}', function ($id) {
        Category::findOrFail($id)->delete();
        return redirect()->route('admin.categories')->with('success', 'Category deleted.');
    })->name('categories.destroy');

    // Subcategories
    Route::get('/subcategories', function () {
        $subcategories = Subcategory::with('category')->orderBy('name')->paginate(20);
        return view('admin.subcategories.index', compact('subcategories'));
    })->name('subcategories');

    Route::get('/subcategories/create', function () {
        $categories = Category::orderBy('name')->get();
        return view('admin.subcategories.form', compact('categories'));
    })->name('subcategories.create');

    Route::post('/subcategories', function (Request $request) {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|max:255',
        ]);
        $validated['slug'] = $request->slug ?: \Illuminate\Support\Str::slug($request->name);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['order'] = $request->order ?? 0;

        Subcategory::create($validated);
        return redirect()->route('admin.subcategories')->with('success', 'Subcategory created.');
    })->name('subcategories.store');

    Route::get('/subcategories/{id}/edit', function ($id) {
        $subcategory = Subcategory::findOrFail($id);
        $categories = Category::orderBy('name')->get();
        return view('admin.subcategories.form', compact('subcategory', 'categories'));
    })->name('subcategories.edit');

    Route::put('/subcategories/{id}', function (Request $request, $id) {
        $subcategory = Subcategory::findOrFail($id);
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|max:255',
        ]);
        $validated['slug'] = $request->slug ?: \Illuminate\Support\Str::slug($request->name);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['order'] = $request->order ?? 0;

        $subcategory->update($validated);
        return redirect()->route('admin.subcategories')->with('success', 'Subcategory updated.');
    })->name('subcategories.update');

    Route::delete('/subcategories/{id}', function ($id) {
        Subcategory::findOrFail($id)->delete();
        return redirect()->route('admin.subcategories')->with('success', 'Subcategory deleted.');
    })->name('subcategories.destroy');

    // Products
    Route::get('/products', function () {
        $products = Product::with('business')->orderBy('name')->paginate(20);
        return view('admin.products.index', compact('products'));
    })->name('products');

    Route::get('/products/create', function () {
        $businesses = Business::orderBy('name')->get();
        return view('admin.products.form', compact('businesses'));
    })->name('products.create');

    Route::post('/products', function (Request $request) {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|max:255',
            'price' => 'nullable|numeric|min:0',
            'availability' => 'nullable|in:in_stock,out_of_stock,pre_order',
        ]);
        $validated['slug'] = $request->slug ?: \Illuminate\Support\Str::slug($request->name);
        $validated['description'] = $request->description;
        $validated['is_active'] = $request->boolean('is_active');

        Product::create($validated);
        return redirect()->route('admin.products')->with('success', 'Product created.');
    })->name('products.store');

    Route::get('/products/{id}/edit', function ($id) {
        $product = Product::findOrFail($id);
        $businesses = Business::orderBy('name')->get();
        return view('admin.products.form', compact('product', 'businesses'));
    })->name('products.edit');

    Route::put('/products/{id}', function (Request $request, $id) {
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|max:255',
            'price' => 'nullable|numeric|min:0',
            'availability' => 'nullable|in:in_stock,out_of_stock,pre_order',
        ]);
        $validated['slug'] = $request->slug ?: \Illuminate\Support\Str::slug($request->name);
        $validated['description'] = $request->description;
        $validated['is_active'] = $request->boolean('is_active');

        $product->update($validated);
        return redirect()->route('admin.products')->with('success', 'Product updated.');
    })->name('products.update');

    Route::delete('/products/{id}', function ($id) {
        Product::findOrFail($id)->delete();
        return redirect()->route('admin.products')->with('success', 'Product deleted.');
    })->name('products.destroy');

    // Claims
    Route::get('/claims', function () {
        $claims = ClaimRequest::with(['user', 'business'])->latest()->paginate(20);
        return view('admin.claims.index', compact('claims'));
    })->name('claims');

    Route::patch('/claims/{id}/approve', function ($id) {
        $claim = ClaimRequest::findOrFail($id);
        $claim->update(['status' => 'approved']);
        $claim->business->update(['claim_status' => 'claimed', 'created_by' => $claim->user_id]);
        return redirect()->route('admin.claims')->with('success', 'Claim approved.');
    })->name('claims.approve');

    Route::patch('/claims/{id}/reject', function ($id) {
        $claim = ClaimRequest::findOrFail($id);
        $claim->update(['status' => 'rejected']);
        return redirect()->route('admin.claims')->with('success', 'Claim rejected.');
    })->name('claims.reject');

    // Reports
    Route::get('/reports', function () {
        $reports = Report::with(['user', 'business'])->latest()->paginate(20);
        return view('admin.reports.index', compact('reports'));
    })->name('reports');

    Route::patch('/reports/{id}/resolve', function ($id) {
        $report = Report::findOrFail($id);
        $report->update(['status' => 'resolved']);
        return redirect()->route('admin.reports')->with('success', 'Report resolved.');
    })->name('reports.resolve');

    // Settings
    Route::get('/settings', function () {
        $all = \App\Models\Setting::orderBy('key')->get()->pluck('value', 'key')->toArray();
        return view('admin.settings.index', ['settings' => $all]);
    })->name('settings');

    Route::put('/settings', function (Request $request) {
        $settings = $request->input('settings', []);
        foreach ($settings as $key => $value) {
            \App\Models\Setting::set($key, $value, str_starts_with($key, 'smtp') ? 'smtp' : 'general');
        }
        return redirect()->route('admin.settings')->with('success', 'Settings saved.');
    })->name('settings.update');

    Route::post('/settings/test-email', function (Request $request) {
        $request->validate(['email' => 'required|email']);

        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Hola SMTP Test\n\nThis is a test email from your Hola app.\n\nIf you received this, your SMTP configuration is working correctly!\n\nSent at: " . now()->format('Y-m-d H:i:s'),
                function ($message) use ($request) {
                    $message->to($request->email)
                            ->subject('Hola - SMTP Test Email')
                            ->from(config('mail.from.address', 'noreply@hola.app'), config('mail.from.name', 'Hola'));
                }
            );

            return response()->json(['message' => 'Test email sent successfully! Check your inbox.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send: ' . $e->getMessage()], 500);
        }
    })->name('settings.test-email');

    // Analytics
    Route::get('/analytics', function () {
        $analytics = [
            'total_views' => Business::sum('views_count'),
            'total_saves' => Business::sum('saves_count'),
            'total_calls' => Business::sum('call_count'),
            'total_whatsapps' => Business::sum('whatsapp_count'),
            'total_directions' => Business::sum('directions_count'),
            'total_shares' => Business::sum('share_count'),
            'total_products' => Product::count(),
            'pending_reports' => Report::where('status', 'pending')->count(),
            'top_businesses' => Business::orderByDesc('views_count')->limit(10)->get(),
            'recent_reports' => Report::with('business')->orderByDesc('created_at')->limit(10)->get(),
        ];
        return view('admin.analytics.index', compact('analytics'));
    })->name('analytics');

    // Featured Businesses
    Route::get('/featured', function () {
        $featured = Business::where('is_featured', true)->with('category')->orderByDesc('views_count')->get();
        return view('admin.featured.index', compact('featured'));
    })->name('featured');

    // ─── AI Agents ───
    $agentCtrl = \App\Http\Controllers\Api\AiAgentController::class;

    Route::get('/agents', function () use ($agentCtrl) {
        $response = (new $agentCtrl())->index();
        $agents = json_decode($response->getContent(), true)['agents'];
        return view('admin.agents.index', compact('agents'));
    })->name('agents');

    Route::get('/agents/create', function () {
        return view('admin.agents.create');
    })->name('agents.create');

    Route::post('/agents', function (\Illuminate\Http\Request $request) use ($agentCtrl) {
        $response = (new $agentCtrl())->store($request);
        return redirect()->route('admin.agents')->with('success', 'Agent created.');
    })->name('agents.store');

    Route::get('/agents/{id}', function ($id) use ($agentCtrl) {
        $response = (new $agentCtrl())->show($id);
        $data = json_decode($response->getContent(), true);
        $agent = $data['agent'];
        $recentTasks = $data['recent_tasks'];
        return view('admin.agents.show', compact('agent', 'recentTasks'));
    })->name('agents.show');

    Route::get('/agents/{id}/edit', function ($id) {
        $agent = \App\Models\AiAgent::findOrFail($id);
        return view('admin.agents.edit', compact('agent'));
    })->name('agents.edit');

    Route::put('/agents/{id}', function ($id, \Illuminate\Http\Request $request) use ($agentCtrl) {
        $response = (new $agentCtrl())->update($request, $id);
        return redirect()->route('admin.agents.show', $id)->with('success', 'Agent updated.');
    })->name('agents.update');

    Route::delete('/agents/{id}', function ($id) use ($agentCtrl) {
        $response = (new $agentCtrl())->destroy($id);
        return redirect()->route('admin.agents')->with('success', 'Agent deleted.');
    })->name('agents.destroy');

    Route::post('/agents/{id}/run', function ($id, \Illuminate\Http\Request $request) use ($agentCtrl) {
        $response = (new $agentCtrl())->runTask($request, $id);
        $data = json_decode($response->getContent(), true);
        return back()->with('success', "Task completed. Imported {$data['result']['imported']} items.");
    })->name('agents.run');

    // ─── Import ───
    $importCtrl = \App\Http\Controllers\Api\ImportController::class;

    Route::get('/import', function () use ($importCtrl) {
        $response = (new $importCtrl())->index();
        $batches = json_decode($response->getContent(), true)['batches']['data'] ?? [];
        return view('admin.import.index', compact('batches'));
    })->name('import');

    Route::get('/import/review', function () {
        $query = \App\Models\ImportItem::where('status', 'pending')->with('batch:id,name,source');
        if (request('batch_id')) $query->where('batch_id', request('batch_id'));
        $items = $query->latest()->paginate(20);

        return view('admin.import.review', compact('items'));
    })->name('import.review');

    Route::post('/import/review/{id}/approve', function ($id) use ($importCtrl) {
        $response = (new $importCtrl())->approve($id);
        return back()->with('success', 'Business created.');
    })->name('import.approve');

    Route::post('/import/review/{id}/reject', function ($id) use ($importCtrl) {
        $response = (new $importCtrl())->reject($id);
        return back()->with('success', 'Item rejected.');
    })->name('import.reject');

    Route::post('/import/approve-all', function () use ($importCtrl) {
        $response = (new $importCtrl())->approveAll(request());
        $data = json_decode($response->getContent(), true);
        return back()->with('success', $data['message']);
    })->name('import.approve-all');
});
