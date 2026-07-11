<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\Business;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\Product;
use App\Models\ClaimRequest;
use App\Models\Report;
use Illuminate\Support\Facades\Hash;

// Public Routes
Route::get('/', function () {
    return view('public.home');
})->name('home');

Route::get('/businesses', function () {
    $query = \App\Models\Business::where('is_active', true)->with('category', 'area');

    if ($search = request('q') ?: request('search')) {
        $safe = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
        $query->where(function ($q) use ($safe) {
            $q->where('name', 'like', $safe)
              ->orWhere('description', 'like', $safe)
              ->orWhere('address', 'like', $safe);
        });
    }

    if ($category = request('category')) {
        $query->whereHas('category', fn($q) => $q->where('slug', $category));
    }

    if ($area = request('area')) {
        $query->whereHas('area', fn($q) => $q->where('slug', $area));
    }

    $sort = request('sort', 'latest');
    $query = match($sort) {
        'rating' => $query->orderByDesc('average_rating'),
        'name' => $query->orderBy('name'),
        default => $query->latest(),
    };

    $businesses = $query->paginate(12)->withQueryString();
    return view('public.businesses', compact('businesses'));
})->name('public.businesses');

Route::get('/categories', function () {
    return view('public.categories');
})->name('public.categories');

Route::get('/areas', function () {
    $areas = \App\Models\Area::active()->where('slug', '!=', 'other')->orderBy('business_count', 'desc')->get();
    return view('public.areas', compact('areas'));
})->name('public.areas');

Route::get('/area/{slug}', function ($slug) {
    $area = \App\Models\Area::where('slug', $slug)->firstOrFail();
    $query = $area->businesses()->active()->with('category');

    if ($category = request('category')) {
        $query->whereHas('category', fn($q) => $q->where('slug', $category));
    }

    $businesses = $query->latest()->paginate(12)->withQueryString();
    return view('public.area', compact('area', 'businesses'));
})->name('public.area');

Route::get('/map', function () {
    $businesses = \Illuminate\Support\Facades\Cache::remember('map_businesses', 300, function () {
        return \App\Models\Business::where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('category')
            ->get();
    });
    return view('public.map', compact('businesses'));
})->name('public.map');

Route::get('/category/{slug}', function ($slug) {
    $category = \App\Models\Category::where('slug', $slug)->firstOrFail();
    $businesses = $category->businesses()->where('is_active', true)->latest()->paginate(12);
    return view('public.category', compact('category', 'businesses'));
})->name('public.category');

Route::get('/business/{slug}', function ($slug) {
    $business = \App\Models\Business::where('slug', $slug)
        ->where('is_active', true)
        ->with(['category', 'products', 'reviews' => fn($q) => $q->with('user:id,name')->latest()])
        ->firstOrFail();
    return view('public.business', compact('business'));
})->name('public.business');

// Login redirect (for auth middleware)
Route::get('/login', fn () => redirect()->route('admin.login'))->name('login');

// robots.txt
Route::get('/robots.txt', function () {
    $siteUrl = config('app.url', 'http://localhost');
    return response()
        ->view('public.robots', compact('siteUrl'))
        ->header('Content-Type', 'text/plain');
});

// Sitemap
Route::get('/sitemap.xml', function () {
    $businesses = \App\Models\Business::where('is_active', true)->select('slug', 'updated_at')->get();
    $categories = \App\Models\Category::select('slug')->get();

    return response()->view('public.sitemap', [
        'businesses' => $businesses,
        'categories' => $categories,
    ], 200, ['Content-Type' => 'application/xml']);
});

// Admin Login
Route::get('/admin/login', fn () => view('auth.login'))->name('admin.login');
Route::post('/admin/login', function (Request $request) {
    $request->validate(['email' => 'required|email', 'password' => 'required']);

    $user = User::where('email', $request->email)->whereIn('role', ['admin', 'super_admin', 'moderator'])->first();

    if (!$user || !Hash::check($request->password, $user->password)) {
        return back()->withErrors(['email' => 'Invalid credentials.']);
    }

    if ($user->banned_at) {
        return back()->withErrors(['email' => 'Your account has been suspended.']);
    }

    if (property_exists($user, 'is_active') && !$user->is_active) {
        return back()->withErrors(['email' => 'Your account is inactive.']);
    }

    Auth::login($user);
    $request->session()->regenerate();

    $user->recordLogin();
    \App\Services\ActivityLogService::log('admin_login', $user);

    return redirect()->route('admin.dashboard');
})->middleware('throttle:5,1')->name('admin.login.post');

Route::post('/admin/logout', function () {
    \App\Services\ActivityLogService::log('admin_logout');
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

    // Users Management
    Route::get('/users', function () {
        $query = User::whereNotIn('role', ['super_admin', 'admin', 'moderator'])->withCount('ownedBusinesses');

        if ($search = request('search')) {
            $safe = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($safe) {
                $q->where('name', 'like', $safe)
                  ->orWhere('email', 'like', $safe)
                  ->orWhere('phone', 'like', $safe);
            });
        }

        if ($role = request('role')) {
            $query->where('role', $role);
        }

        if ($status = request('status')) {
            if ($status === 'banned') {
                $query->whereNotNull('banned_at');
            } elseif ($status === 'active') {
                $query->where('is_active', true)->whereNull('banned_at');
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if (request()->has('verified') && request('verified') !== '') {
            if (request('verified') === '1') {
                $query->where(function ($q) {
                    $q->whereNotNull('email_verified_at')->orWhereNotNull('phone_verified_at');
                });
            } else {
                $query->whereNull('email_verified_at')->whereNull('phone_verified_at');
            }
        }

        $users = $query->latest()->paginate(20)->withQueryString();
        return view('admin.users.index', compact('users'));
    })->name('users');

    Route::get('/users/create', function () {
        return view('admin.users.form');
    })->name('users.create');

    Route::post('/users', function (Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|unique:users,phone',
            'password' => 'required|string|min:6',
            'role' => 'required|in:customer,owner',
            'is_active' => 'boolean',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => $request->password,
            'role' => $request->role,
            'is_active' => $request->boolean('is_active', true),
            'created_by_admin' => Auth::id(),
        ]);

        \App\Services\ActivityLogService::log('user_created', $user, ['role' => $user->role]);

        return redirect()->route('admin.users.show', $user->id)->with('success', 'User created.');
    })->name('users.store');

    Route::get('/users/{id}', function ($id) {
        $user = User::with(['ownedBusinesses', 'reviews', 'savedListings', 'reports', 'claimRequests', 'conversations'])->findOrFail($id);
        return view('admin.users.show', compact('user'));
    })->name('users.show');

    Route::get('/users/{id}/edit', function ($id) {
        $user = User::findOrFail($id);
        return view('admin.users.form', compact('user'));
    })->name('users.edit');

    Route::put('/users/{id}', function (Request $request, $id) {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|unique:users,phone,' . $user->id,
            'role' => 'required|in:customer,owner',
            'is_active' => 'boolean',
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'role' => $request->role,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('password')) {
            $updateData['password'] = $request->password;
        }

        $user->update($updateData);
        \App\Services\ActivityLogService::log('user_updated', $user);

        return redirect()->route('admin.users.show', $user->id)->with('success', 'User updated.');
    })->name('users.update');

    Route::delete('/users/{id}', function ($id) {
        $user = User::findOrFail($id);

        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Cannot delete super admin.');
        }

        $user->delete();
        \App\Services\ActivityLogService::log('user_deleted', $user, ['name' => $user->name]);
        return redirect()->route('admin.users')->with('success', 'User deleted.');
    })->name('users.destroy');

    Route::post('/users/{id}/ban', function (Request $request, $id) {
        $user = User::findOrFail($id);
        $user->ban($request->input('reason'));
        \App\Services\ActivityLogService::log('user_banned', $user, ['reason' => $request->input('reason')]);
        return back()->with('success', 'User banned.');
    })->name('users.ban');

    Route::post('/users/{id}/unban', function ($id) {
        $user = User::findOrFail($id);
        $user->unban();
        \App\Services\ActivityLogService::log('user_unbanned', $user);
        return back()->with('success', 'User unbanned.');
    })->name('users.unban');

    Route::post('/users/bulk', function (Request $request) {
        $action = $request->input('action');
        $ids = json_decode($request->input('ids', '[]'), true);

        if (empty($ids)) return back()->with('error', 'No users selected.');

        $users = User::whereIn('id', $ids)->get();
        $count = 0;

        foreach ($users as $user) {
            if ($user->isSuperAdmin()) continue;

            switch ($action) {
                case 'activate':
                    $user->update(['is_active' => true]);
                    $count++;
                    break;
                case 'deactivate':
                    $user->update(['is_active' => false]);
                    $count++;
                    break;
                case 'ban':
                    $user->ban('Bulk ban');
                    $count++;
                    break;
                case 'unban':
                    $user->unban();
                    $count++;
                    break;
            }
        }

        return back()->with('success', "Updated {$count} users.");
    })->name('users.bulk');

    // Businesses
    Route::get('/businesses', function () {
        $query = Business::with('category');

        if ($search = request('search')) {
            $safe = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($safe) {
                $q->where('name', 'like', $safe)
                  ->orWhere('address', 'like', $safe)
                  ->orWhere('locality', 'like', $safe);
            });
        }

        if ($categoryId = request('category')) {
            $query->where('category_id', $categoryId);
        }

        if ($status = request('status')) {
            $query->where('is_active', $status === 'active');
        }

        if (request()->has('featured') && request('featured') !== '') {
            $query->where('is_featured', request('featured') === '1');
        }

        $businesses = $query->latest()->paginate(20)->withQueryString();
        return view('admin.businesses.index', compact('businesses'));
    })->name('businesses');

    // Detect business changes
    Route::post('/businesses/detect-changes', function () {
        Artisan::call('app:detect-business-changes', ['--limit' => request('limit', 50)]);
        $output = Artisan::output();
        return response()->json(['message' => 'Change detection completed', 'output' => $output]);
    })->name('businesses.detect-changes');

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
        $validated['created_by'] = Auth::id();

        Business::create($validated);
        \App\Services\ActivityLogService::log('business_created', null, ['name' => $validated['name']]);

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
        \App\Services\ActivityLogService::log('business_updated', $business);

        return redirect()->route('admin.businesses')->with('success', 'Business updated.');
    })->name('businesses.update');

    Route::delete('/businesses/{id}', function ($id) {
        Business::findOrFail($id)->delete();
        \App\Services\ActivityLogService::log('business_deleted', null, ['id' => $id]);
        return redirect()->route('admin.businesses')->with('success', 'Business deleted.');
    })->name('businesses.destroy');

    // Bulk business actions
    Route::post('/businesses/bulk', function (\Illuminate\Http\Request $request) {
        $action = $request->input('action');
        $ids = json_decode($request->input('ids', '[]'), true);

        if (empty($ids)) {
            return back()->with('error', 'No items selected.');
        }

        switch ($action) {
            case 'activate':
                Business::whereIn('id', $ids)->update(['is_active' => true]);
                return back()->with('success', count($ids) . ' businesses activated.');
            case 'deactivate':
                Business::whereIn('id', $ids)->update(['is_active' => false]);
                return back()->with('success', count($ids) . ' businesses deactivated.');
            case 'feature':
                Business::whereIn('id', $ids)->update(['is_featured' => true]);
                return back()->with('success', count($ids) . ' businesses featured.');
            case 'unfeature':
                Business::whereIn('id', $ids)->update(['is_featured' => false]);
                return back()->with('success', count($ids) . ' businesses unfeatured.');
            case 'delete':
                Business::whereIn('id', $ids)->delete();
                return back()->with('success', count($ids) . ' businesses deleted.');
            default:
                return back()->with('error', 'Unknown action.');
        }
    })->name('businesses.bulk');

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
            'availability' => 'nullable|in:in_stock,out_of_stock,limited',
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
            'availability' => 'nullable|in:in_stock,out_of_stock,limited',
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
        $query = ClaimRequest::with(['user', 'business']);

        if ($status = request('status')) {
            $query->where('status', $status);
        }

        $claims = $query->latest()->paginate(20)->withQueryString();
        return view('admin.claims.index', compact('claims'));
    })->name('claims');

    Route::patch('/claims/{id}/approve', function ($id) {
        $claim = ClaimRequest::with(['user', 'business'])->findOrFail($id);
        $claim->update(['status' => 'approved']);
        $claim->business->update(['claim_status' => 'claimed', 'created_by' => $claim->user_id]);
        if ($claim->user->role === 'customer') {
            $claim->user->update(['role' => 'owner']);
        }
        \App\Services\NotificationService::claimApproved($claim);
        \App\Services\ActivityLogService::log('claim_approved', $claim, ['business_id' => $claim->business_id, 'user_id' => $claim->user_id]);
        return redirect()->route('admin.claims')->with('success', 'Claim approved. User upgraded to owner.');
    })->name('claims.approve');

    Route::patch('/claims/{id}/reject', function ($id) {
        $claim = ClaimRequest::with(['user', 'business'])->findOrFail($id);
        $claim->update(['status' => 'rejected']);
        \App\Services\NotificationService::claimRejected($claim);
        \App\Services\ActivityLogService::log('claim_rejected', $claim, ['business_id' => $claim->business_id]);
        return redirect()->route('admin.claims')->with('success', 'Claim rejected.');
    })->name('claims.reject');

    // Bulk claim actions
    Route::post('/claims/bulk-approve', function (\Illuminate\Http\Request $request) {
        $ids = json_decode($request->input('ids', '[]'), true);
        if (empty($ids)) return back()->with('error', 'No items selected.');

        foreach ($ids as $id) {
            $claim = ClaimRequest::with(['user', 'business'])->findOrFail($id);
            $claim->update(['status' => 'approved']);
            $claim->business->update(['claim_status' => 'claimed', 'created_by' => $claim->user_id]);
            if ($claim->user->role === 'customer') {
                $claim->user->update(['role' => 'owner']);
            }
            \App\Services\NotificationService::claimApproved($claim);
            \App\Services\ActivityLogService::log('claim_approved', $claim, ['business_id' => $claim->business_id, 'user_id' => $claim->user_id]);
        }
        return back()->with('success', count($ids) . ' claims approved. Users upgraded to owner.');
    })->name('claims.bulk-approve');

    Route::post('/claims/bulk-reject', function (\Illuminate\Http\Request $request) {
        $ids = json_decode($request->input('ids', '[]'), true);
        if (empty($ids)) return back()->with('error', 'No items selected.');

        foreach ($ids as $id) {
            $claim = ClaimRequest::with('business')->findOrFail($id);
            $claim->update(['status' => 'rejected']);
            \App\Services\NotificationService::claimRejected($claim);
            \App\Services\ActivityLogService::log('claim_rejected', $claim, ['business_id' => $claim->business_id]);
        }
        return back()->with('success', count($ids) . ' claims rejected.');
    })->name('claims.bulk-reject');

    // Reports
    Route::get('/reports', function () {
        $query = Report::with(['user', 'business']);

        if ($status = request('status')) {
            $query->where('status', $status);
        }

        if ($type = request('type')) {
            $query->where('type', $type);
        }

        $reports = $query->latest()->paginate(20)->withQueryString();
        return view('admin.reports.index', compact('reports'));
    })->name('reports');

    Route::patch('/reports/{id}/resolve', function ($id) {
        $report = Report::findOrFail($id);
        $report->update(['status' => 'resolved']);
        return redirect()->route('admin.reports')->with('success', 'Report resolved.');
    })->name('reports.resolve');

    // Bulk report actions
    Route::post('/reports/bulk-resolve', function (\Illuminate\Http\Request $request) {
        $ids = json_decode($request->input('ids', '[]'), true);
        if (empty($ids)) return back()->with('error', 'No items selected.');

        Report::whereIn('id', $ids)->update(['status' => 'resolved']);
        return back()->with('success', count($ids) . ' reports resolved.');
    })->name('reports.bulk-resolve');

    // Settings
    Route::get('/settings', function () {
        $all = \App\Models\Setting::orderBy('key')->get()->pluck('value', 'key')->toArray();
        return view('admin.settings.index', ['settings' => $all]);
    })->name('settings');

    Route::put('/settings', function (Request $request) {
        $settings = $request->input('settings', []);
        foreach ($settings as $key => $value) {
            \App\Models\Setting::set($key, $value, str_starts_with($key, 'smtp') ? 'smtp' : (str_starts_with($key, 'api_key') ? 'api' : 'general'));
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

    Route::post('/settings/test-telegram', function (Request $request) {
        $request->validate(['message' => 'required|string']);

        $token = \App\Models\Setting::get('telegram_bot_token');
        $chatId = \App\Models\Setting::get('telegram_chat_id');

        if (!$token || !$chatId) {
            return response()->json(['message' => 'Telegram bot token and chat ID not configured.'], 422);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $request->message,
                'parse_mode' => 'HTML',
            ]);

            if ($response->successful()) {
                return response()->json(['message' => 'Test Telegram message sent successfully!']);
            }

            return response()->json(['message' => 'Failed: ' . ($response->json('description') ?? 'Unknown error')], 500);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed: ' . $e->getMessage()], 500);
        }
    })->name('settings.test-telegram');

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
            'total_businesses' => Business::count(),
            'total_users' => User::count(),
            'total_reviews' => \App\Models\Review::count(),
            'total_claims' => \App\Models\ClaimRequest::count(),
            'pending_claims' => \App\Models\ClaimRequest::where('status', 'pending')->count(),
            'pending_reports' => Report::where('status', 'pending')->count(),
            'pending_imports' => \App\Models\ImportItem::where('status', 'pending')->count(),
            'active_businesses' => Business::where('is_active', true)->count(),
            'featured_businesses' => Business::where('is_featured', true)->count(),
            'top_businesses' => Business::orderByDesc('views_count')->limit(10)->get(),
            'recent_reports' => Report::with('business')->orderByDesc('created_at')->limit(10)->get(),
            // Category distribution
            'category_distribution' => Category::withCount('businesses')->orderByDesc('businesses_count')->get(),
            // User growth (last 30 days)
            'user_growth' => User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date'),
            // Business growth (last 30 days)
            'business_growth' => Business::selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('count', 'date'),
            'businessesMaxViews' => Business::max('views_count') ?: 1,
        ];

        $businessesMaxViews = $analytics['businessesMaxViews'];
        return view('admin.analytics.index', compact('analytics', 'businessesMaxViews'));
    })->name('analytics');

    // Featured Businesses
    Route::get('/featured', function () {
        $featured = Business::where('is_featured', true)->with('category')->orderByDesc('views_count')->get();
        return view('admin.featured.index', compact('featured'));
    })->name('featured');

    // ─── AI Agents ───
    $agentCtrl = \App\Http\Controllers\Api\AiAgentController::class;

    Route::get('/autopilot', function () {
        $agent = \App\Models\AiAgent::first();
        $recentTasks = $agent ? $agent->tasks()->latest()->take(20)->get() : collect();
        $pendingImports = \App\Models\ImportItem::where('status', 'pending')->count();
        $totalBusinesses = \App\Models\Business::where('is_active', true)->count();
        $totalCategories = \App\Models\Category::count();
        $todaysTasks = $agent ? $agent->tasks()->where('created_at', '>=', now()->startOfDay())->count() : 0;
        $todaysImports = $agent ? $agent->tasks()->where('created_at', '>=', now()->startOfDay())->sum('imported_count') : 0;
        $lastRun = $agent ? $agent->tasks()->latest()->first() : null;
        $nextRun = now()->addMinutes(240 - (now()->timestamp % 240));

        return view('admin.autopilot', compact(
            'agent', 'recentTasks', 'pendingImports', 'totalBusinesses',
            'totalCategories', 'todaysTasks', 'todaysImports', 'lastRun', 'nextRun'
        ));
    })->name('autopilot');

    Route::post('/autopilot/toggle', function (\Illuminate\Http\Request $request) {
        $agent = \App\Models\AiAgent::first();
        if (!$agent) return back()->with('error', 'No agent found.');

        $agent->update(['status' => $agent->status === 'active' ? 'paused' : 'active']);

        \App\Models\ActivityLog::create([
            'action' => 'autopilot_toggled',
            'user_id' => auth()->id(),
            'properties' => ['new_status' => $agent->status],
        ]);

        return back()->with('success', "Autopilot {$agent->status}.");
    })->name('autopilot.toggle');

    Route::post('/autopilot/prompt', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'system_prompt' => 'required|string|max:5000',
        ]);

        $agent = \App\Models\AiAgent::first();
        if (!$agent) return back()->with('error', 'No agent found.');

        $agent->update(['system_prompt' => $request->system_prompt]);

        return back()->with('success', 'Agent rules updated.');
    })->name('autopilot.prompt');

    Route::post('/autopilot/location', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'search_district' => 'required|string|max:255',
            'search_state' => 'required|string|max:255',
            'search_zipcodes' => 'required|string|max:500',
            'search_areas' => 'required|string|max:500',
        ]);

        \App\Models\Setting::set('search_district', $request->search_district, 'search');
        \App\Models\Setting::set('search_state', $request->search_state, 'search');
        \App\Models\Setting::set('search_zipcodes', $request->search_zipcodes, 'search');
        \App\Models\Setting::set('search_areas', $request->search_areas, 'search');

        return back()->with('success', 'Search locations updated. Agent will use these on next run.');
    })->name('autopilot.location');

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

    Route::get('/search-history', function () {
        $history = \App\Models\SearchHistory::with('agent:id,name,avatar')
            ->latest()
            ->paginate(20);
        return view('admin.search-history', compact('history'));
    })->name('search-history');

    Route::get('/import', function () {
        $batches = \App\Models\ImportBatch::withCount('items')
            ->with('agent:id,name,avatar')
            ->latest()
            ->paginate(20);
        return view('admin.import.index', compact('batches'));
    })->name('import');

    Route::get('/import/review', function () {
        $status = request('status', 'pending');
        $query = \App\Models\ImportItem::whereIn('status', [$status, 'duplicate'])->with('batch:id,name,source');
        if (request('batch_id')) $query->where('batch_id', request('batch_id'));
        if ($status === 'all') {
            $query->whereIn('status', ['pending', 'duplicate']);
        }
        $items = $query->latest()->paginate(20);
        return view('admin.import.review', compact('items', 'status'));
    })->name('import.review');

    Route::post('/import/review/{id}/approve', function ($id) {
        $item = \App\Models\ImportItem::with('batch')->findOrFail($id);
        $data = $item->data;

        // DUPLICATE CHECK
        $existingBusiness = null;

        // Check by external_id
        if (!empty($item->external_id)) {
            $existingBusiness = \App\Models\Business::withoutTrashed()->where('external_id', $item->external_id)->first();
        }

        // Check by name + address
        if (!$existingBusiness && !empty($data['name'])) {
            $existingBusiness = \App\Models\Business::withoutTrashed()->whereRaw('LOWER(name) = ?', [strtolower(trim($data['name'], " \t\n\r\0\x0B,"))])->first();
            if ($existingBusiness && !empty($data['address'])) {
                similar_text(strtolower($existingBusiness->address), strtolower($data['address']), $percent);
                if ($percent < 50) {
                    $existingBusiness = null;
                }
            }
        }

        // Check by phone
        if (!$existingBusiness && !empty($data['phone'])) {
            $normalizedPhone = str_replace([' ', '-', '(', ')', '+'], '', $data['phone']);
            $existingBusiness = \App\Models\Business::withoutTrashed()->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') = ?", [$normalizedPhone])->first();
        }

        if ($existingBusiness) {
            $item->update(['status' => 'rejected', 'notes' => "Duplicate: {$existingBusiness->name}"]);
            if ($item->batch) {
                $item->batch->increment('rejected');
                $item->batch->decrement('pending');
            }
            return back()->with('error', "Skipped: Business already exists ({$existingBusiness->name})");
        }

        $categories = \App\Models\Category::pluck('id', 'name')->toArray();

        $categoryName = $data['category'] ?? $data['type'] ?? null;
        $categoryId = matchImportCategory($categoryName, $categories);

        // Detect area
        $areaId = $data['area_id'] ?? null;
        if (!$areaId && !empty($data['latitude']) && !empty($data['longitude'])) {
            $area = \App\Models\Area::findByCoordinates($data['latitude'], $data['longitude']);
            if ($area) $areaId = $area->id;
        }
        if (!$areaId) {
            $otherArea = \App\Models\Area::where('slug', 'other')->where('is_active', true)->first();
            if ($otherArea) $areaId = $otherArea->id;
        }

        $slug = \Illuminate\Support\Str::slug(trim($data['name'] ?? 'unknown-business', " \t\n\r\0\x0B,"));
        $existing = \App\Models\Business::withTrashed()->where('slug', $slug)->first();
        if ($existing) {
            $slug .= '-' . \Illuminate\Support\Str::random(5);
        }

        \App\Models\Business::create([
            'name' => $data['name'] ?? 'Unknown Business',
            'slug' => $slug,
            'category_id' => $categoryId,
            'area_id' => $areaId,
            'description' => $data['description'] ?? null,
            'address' => $data['address'] ?? $data['location'] ?? '',
            'locality' => $data['locality'] ?? null,
            'district' => $data['district'] ?? 'Churachandpur',
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'latitude' => $data['latitude'] ?? $data['lat'] ?? null,
            'longitude' => $data['longitude'] ?? $data['lng'] ?? null,
            'working_hours' => $data['working_hours'] ?? null,
            'average_rating' => $data['rating'] ?? 0,
            'review_count' => $data['total_ratings'] ?? 0,
            'is_active' => true,
            'source' => 'import',
            'external_id' => $item->external_id,
            'import_batch_id' => $item->batch_id,
            'confidence' => $item->confidence,
            'photos' => !empty($data['photos']) && is_array($data['photos']) ? $data['photos'] : null,
        ]);

        $newBusiness = \App\Models\Business::where('slug', $slug)->first();
        if ($newBusiness && $item->batch && $item->batch->agent_id) {
            try {
                \App\Models\AgentImportedBusiness::create([
                    'agent_id' => $item->batch->agent_id,
                    'business_id' => $newBusiness->id,
                    'batch_id' => $item->batch_id,
                    'google_place_id' => $item->external_id,
                    'business_name' => $data['name'] ?? 'Unknown Business',
                    'address' => $data['address'] ?? null,
                    'imported_at' => now(),
                ]);
            } catch (\Exception $e) { /* memory failure should not block approve */ }
        }

        $item->update(['status' => 'approved']);
        if ($item->batch) {
            $item->batch->increment('approved');
            $item->batch->decrement('pending');
        }

        return back()->with('success', 'Business created from import.');
    })->name('import.approve');

    Route::post('/import/review/{id}/reject', function ($id) {
        $item = \App\Models\ImportItem::findOrFail($id);
        $item->update(['status' => 'rejected']);
        if ($item->batch) {
            $item->batch->increment('rejected');
            $item->batch->decrement('pending');
        }
        return back()->with('success', 'Item rejected.');
    })->name('import.reject');

    // Bulk import actions
    Route::post('/import/bulk-approve', function (\Illuminate\Http\Request $request) {
        set_time_limit(120);
        $ids = json_decode($request->input('ids', '[]'), true);
        if (empty($ids)) return back()->with('error', 'No items selected.');

        $approved = 0;
        $skipped = 0;
        foreach ($ids as $id) {
            try {
                $item = \App\Models\ImportItem::with('batch')->findOrFail($id);
                $data = $item->data;

                // DUPLICATE CHECK
                $existingBusiness = null;

                // Check by external_id
                if (!empty($item->external_id)) {
                    $existingBusiness = \App\Models\Business::withoutTrashed()->where('external_id', $item->external_id)->first();
                }

                // Check by name + address
                if (!$existingBusiness && !empty($data['name'])) {
                    $existingBusiness = \App\Models\Business::withoutTrashed()->whereRaw('LOWER(name) = ?', [strtolower(trim($data['name'], " \t\n\r\0\x0B,"))])->first();
                    if ($existingBusiness && !empty($data['address'])) {
                        similar_text(strtolower($existingBusiness->address), strtolower($data['address']), $percent);
                        if ($percent < 50) {
                            $existingBusiness = null;
                        }
                    }
                }

                // Check by phone
                if (!$existingBusiness && !empty($data['phone'])) {
                    $normalizedPhone = str_replace([' ', '-', '(', ')', '+'], '', $data['phone']);
                    $existingBusiness = \App\Models\Business::withoutTrashed()->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') = ?", [$normalizedPhone])->first();
                }

                if ($existingBusiness) {
                    $item->update(['status' => 'rejected', 'notes' => "Duplicate: {$existingBusiness->name}"]);
                    if ($item->batch) {
                    $item->batch->increment('rejected');
                    $item->batch->decrement('pending');
                    }
                    $skipped++;
                    continue;
                }

                $categories = \App\Models\Category::pluck('id', 'name')->toArray();
                $categoryName = $data['category'] ?? $data['type'] ?? null;
                $categoryId = matchImportCategory($categoryName, $categories);

                // Detect area
                $areaId = $data['area_id'] ?? null;
                if (!$areaId && !empty($data['latitude']) && !empty($data['longitude'])) {
                    $area = \App\Models\Area::findByCoordinates($data['latitude'], $data['longitude']);
                    if ($area) $areaId = $area->id;
                }
                if (!$areaId) {
                    $otherArea = \App\Models\Area::where('slug', 'other')->where('is_active', true)->first();
                    if ($otherArea) $areaId = $otherArea->id;
                }

                $slug = \Illuminate\Support\Str::slug(trim($data['name'] ?? 'unknown-business', " \t\n\r\0\x0B,"));
                $existing = \App\Models\Business::withTrashed()->where('slug', $slug)->first();
                if ($existing) {
                    $slug .= '-' . \Illuminate\Support\Str::random(5);
                }

                \App\Models\Business::create([
                    'name' => $data['name'] ?? 'Unknown Business',
                    'slug' => $slug,
                    'category_id' => $categoryId,
                    'area_id' => $areaId,
                    'description' => $data['description'] ?? null,
                    'address' => $data['address'] ?? $data['location'] ?? '',
                    'locality' => $data['locality'] ?? null,
                    'district' => $data['district'] ?? 'Churachandpur',
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'website' => $data['website'] ?? null,
                    'latitude' => $data['latitude'] ?? $data['lat'] ?? null,
                    'longitude' => $data['longitude'] ?? $data['lng'] ?? null,
                    'working_hours' => $data['working_hours'] ?? null,
                    'average_rating' => $data['rating'] ?? 0,
                    'review_count' => $data['total_ratings'] ?? 0,
                    'is_active' => true,
                    'source' => 'import',
                    'external_id' => $item->external_id,
                    'import_batch_id' => $item->batch_id,
                    'confidence' => $item->confidence,
                    'photos' => !empty($data['photos']) && is_array($data['photos']) ? $data['photos'] : null,
                ]);

                $newBusiness = \App\Models\Business::where('slug', $slug)->first();
                if ($newBusiness && $item->batch && $item->batch->agent_id) {
                    \App\Models\AgentImportedBusiness::create([
                        'agent_id' => $item->batch->agent_id,
                        'business_id' => $newBusiness->id,
                        'batch_id' => $item->batch_id,
                        'google_place_id' => $item->external_id,
                        'business_name' => $data['name'] ?? 'Unknown Business',
                        'address' => $data['address'] ?? null,
                        'imported_at' => now(),
                    ]);
                }

                $item->update(['status' => 'approved']);
                if ($item->batch) {
                    $item->batch->increment('approved');
                    $item->batch->decrement('pending');
                }
                $approved++;
            } catch (\Exception $e) { continue; }
        }
        $message = "Approved {$approved} items.";
        if ($skipped > 0) $message .= " Skipped {$skipped} duplicates.";

        if ($approved > 0) {
            \Illuminate\Support\Facades\Artisan::call('photos:download', ['--limit' => $approved]);
        }

        return back()->with('success', $message);
    })->name('import.bulk-approve');

    Route::post('/import/bulk-reject', function (\Illuminate\Http\Request $request) {
        $ids = json_decode($request->input('ids', '[]'), true);
        if (empty($ids)) return back()->with('error', 'No items selected.');

        foreach ($ids as $id) {
            $item = \App\Models\ImportItem::findOrFail($id);
            $item->update(['status' => 'rejected']);
            if ($item->batch) {
                $item->batch->increment('rejected');
                $item->batch->decrement('pending');
            }
        }
        return back()->with('success', count($ids) . ' items rejected.');
    })->name('import.bulk-reject');

    Route::post('/import/approve-all', function () {
        set_time_limit(120);
        $items = \App\Models\ImportItem::where('status', 'pending')->with('batch')->get();
        $approved = 0;
        $skipped = 0;

        foreach ($items as $item) {
            try {
                $data = $item->data;

                // DUPLICATE CHECK
                $existingBusiness = null;

                // Check by external_id
                if (!empty($item->external_id)) {
                    $existingBusiness = \App\Models\Business::withoutTrashed()->where('external_id', $item->external_id)->first();
                }

                // Check by name + address
                if (!$existingBusiness && !empty($data['name'])) {
                    $existingBusiness = \App\Models\Business::withoutTrashed()->whereRaw('LOWER(name) = ?', [strtolower(trim($data['name'], " \t\n\r\0\x0B,"))])->first();
                    if ($existingBusiness && !empty($data['address'])) {
                        similar_text(strtolower($existingBusiness->address), strtolower($data['address']), $percent);
                        if ($percent < 50) {
                            $existingBusiness = null;
                        }
                    }
                }

                // Check by phone
                if (!$existingBusiness && !empty($data['phone'])) {
                    $normalizedPhone = str_replace([' ', '-', '(', ')', '+'], '', $data['phone']);
                    $existingBusiness = \App\Models\Business::withoutTrashed()->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') = ?", [$normalizedPhone])->first();
                }

                if ($existingBusiness) {
                    $item->update(['status' => 'rejected', 'notes' => "Duplicate: {$existingBusiness->name}"]);
                    if ($item->batch) {
                        $item->batch->increment('rejected');
                        $item->batch->decrement('pending');
                    }
                    $skipped++;
                    continue;
                }

                $categories = \App\Models\Category::pluck('id', 'name')->toArray();
                $categoryName = $data['category'] ?? $data['type'] ?? null;
                $categoryId = matchImportCategory($categoryName, $categories);

                // Detect area
                $areaId = $data['area_id'] ?? null;
                if (!$areaId && !empty($data['latitude']) && !empty($data['longitude'])) {
                    $area = \App\Models\Area::findByCoordinates($data['latitude'], $data['longitude']);
                    if ($area) $areaId = $area->id;
                }
                if (!$areaId) {
                    $otherArea = \App\Models\Area::where('slug', 'other')->where('is_active', true)->first();
                    if ($otherArea) $areaId = $otherArea->id;
                }

                $slug = \Illuminate\Support\Str::slug(trim($data['name'] ?? 'unknown-business', " \t\n\r\0\x0B,"));
                $existing = \App\Models\Business::withTrashed()->where('slug', $slug)->first();
                if ($existing) {
                    $slug .= '-' . \Illuminate\Support\Str::random(5);
                }

                \App\Models\Business::create([
                    'name' => $data['name'] ?? 'Unknown Business',
                    'slug' => $slug,
                    'category_id' => $categoryId,
                    'area_id' => $areaId,
                    'description' => $data['description'] ?? null,
                    'address' => $data['address'] ?? $data['location'] ?? '',
                    'locality' => $data['locality'] ?? null,
                    'district' => $data['district'] ?? 'Churachandpur',
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'website' => $data['website'] ?? null,
                    'latitude' => $data['latitude'] ?? $data['lat'] ?? null,
                    'longitude' => $data['longitude'] ?? $data['lng'] ?? null,
                    'working_hours' => $data['working_hours'] ?? null,
                    'average_rating' => $data['rating'] ?? 0,
                    'review_count' => $data['total_ratings'] ?? 0,
                    'is_active' => true,
                    'source' => 'import',
                    'external_id' => $item->external_id,
                    'import_batch_id' => $item->batch_id,
                    'confidence' => $item->confidence,
                    'photos' => !empty($data['photos']) && is_array($data['photos']) ? $data['photos'] : null,
                ]);

                $newBusiness = \App\Models\Business::where('slug', $slug)->first();
                if ($newBusiness && $item->batch && $item->batch->agent_id) {
                    \App\Models\AgentImportedBusiness::create([
                        'agent_id' => $item->batch->agent_id,
                        'business_id' => $newBusiness->id,
                        'batch_id' => $item->batch_id,
                        'google_place_id' => $item->external_id,
                        'business_name' => $data['name'] ?? 'Unknown Business',
                        'address' => $data['address'] ?? null,
                        'imported_at' => now(),
                    ]);
                }

                $item->update(['status' => 'approved']);
                if ($item->batch) {
                    $item->batch->increment('approved');
                    $item->batch->decrement('pending');
                }
                $approved++;
            } catch (\Exception $e) {
                continue;
            }
        }

        $message = "Approved {$approved} items.";
        if ($skipped > 0) $message .= " Skipped {$skipped} duplicates.";

        if ($approved > 0) {
            \Illuminate\Support\Facades\Artisan::call('photos:download', ['--limit' => $approved]);
        }

        return back()->with('success', $message);
    })->name('import.approve-all');

    // CSV Upload
    Route::post('/import/csv', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $csvData = array_map('str_getcsv', file($file->getRealPath()));
        $headers = array_shift($csvData);

        $batch = \App\Models\ImportBatch::create([
            'agent_id' => null,
            'source' => 'csv',
            'name' => 'CSV: ' . $file->getClientOriginalName(),
            'total' => count($csvData),
            'status' => 'processing',
            'pending' => count($csvData),
        ]);

        $imported = 0;
        $skipped = 0;

        // Pre-load existing for duplicate detection (exclude soft-deleted)
        $existingNames = \App\Models\Business::withoutTrashed()->pluck('name')->map(fn($n) => strtolower($n))->toArray();

        foreach ($csvData as $row) {
            $data = array_combine($headers, $row);

            $name = $data['name'] ?? $data['business_name'] ?? null;
            if (!$name) {
                $skipped++;
                continue;
            }

            // Duplicate check
            if (in_array(strtolower(trim($name)), $existingNames)) {
                $skipped++;
                continue;
            }

            \App\Models\ImportItem::create([
                'batch_id' => $batch->id,
                'data' => [
                    'name' => $name,
                    'address' => $data['address'] ?? $data['location'] ?? '',
                    'phone' => $data['phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'website' => $data['website'] ?? null,
                    'description' => $data['description'] ?? null,
                    'category' => $data['category'] ?? null,
                    'latitude' => $data['latitude'] ?? $data['lat'] ?? null,
                    'longitude' => $data['longitude'] ?? $data['lng'] ?? null,
                ],
                'confidence' => 0.8,
            ]);

            $existingNames[] = strtolower(trim($name));
            $imported++;
        }

        $batch->update([
            'status' => 'completed',
            'pending' => $imported,
        ]);

        $message = "Imported {$imported} items from CSV.";
        if ($skipped > 0) $message .= " Skipped {$skipped} duplicates/invalid.";
        return back()->with('success', $message);
    })->name('import.csv');

    // ─── Vendors (Owner Management) ─── admin + super_admin only
    Route::get('/vendors', function () {
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) abort(403);

        $query = User::where('role', 'owner')->withCount('ownedBusinesses');

        if ($search = request('search')) {
            $safe = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
            $query->where(function ($q) use ($safe) {
                $q->where('name', 'like', $safe)
                  ->orWhere('email', 'like', $safe)
                  ->orWhere('phone', 'like', $safe);
            });
        }

        if ($status = request('status')) {
            if ($status === 'banned') {
                $query->whereNotNull('banned_at');
            } elseif ($status === 'active') {
                $query->where('is_active', true)->whereNull('banned_at');
            }
        }

        $vendors = $query->latest()->paginate(20)->withQueryString();
        return view('admin.vendors.index', compact('vendors'));
    })->name('vendors');

    Route::get('/vendors/{id}', function ($id) {
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) abort(403);

        $vendor = User::where('role', 'owner')->findOrFail($id);
        $businesses = Business::where('created_by', $vendor->id)->with('category')->get();
        $recentActivity = \App\Models\ActivityLog::where('user_id', $vendor->id)->latest()->take(10)->get();

        return view('admin.vendors.show', compact('vendor', 'businesses', 'recentActivity'));
    })->name('vendors.show');

    // ─── Staff Management ─── super_admin only
    Route::get('/staff', function () {
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) abort(403);

        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator'])->latest()->paginate(20)->withQueryString();
        return view('admin.staff.index', compact('staff'));
    })->name('staff');

    Route::get('/staff/create', function () {
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) abort(403);
        return view('admin.staff.form', ['staff' => null]);
    })->name('staff.create');

    Route::post('/staff', function (Request $request) {
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) abort(403);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:moderator,admin,super_admin',
            'is_active' => 'boolean',
        ]);

        $staff = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => $request->role,
            'is_active' => $request->boolean('is_active', true),
            'created_by_admin' => Auth::id(),
        ]);

        \App\Services\ActivityLogService::log('staff_created', $staff, ['role' => $staff->role]);

        return redirect()->route('admin.staff')->with('success', 'Staff member created.');
    })->name('staff.store');

    Route::get('/staff/{id}', function ($id) {
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) abort(403);

        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator'])->findOrFail($id);
        return view('admin.staff.show', compact('staff'));
    })->name('staff.show');

    Route::get('/staff/{id}/edit', function ($id) {
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) abort(403);

        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator'])->findOrFail($id);
        return view('admin.staff.form', compact('staff'));
    })->name('staff.edit');

    Route::put('/staff/{id}', function (Request $request, $id) {
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) abort(403);

        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator'])->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $staff->id,
            'role' => 'required|in:moderator,admin,super_admin',
            'is_active' => 'boolean',
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('password')) {
            $updateData['password'] = $request->password;
        }

        $staff->update($updateData);
        \App\Services\ActivityLogService::log('staff_updated', $staff);

        return redirect()->route('admin.staff')->with('success', 'Staff member updated.');
    })->name('staff.update');

    Route::delete('/staff/{id}', function ($id) {
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) abort(403);

        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator'])->findOrFail($id);

        if ($staff->id === Auth::id()) {
            return back()->with('error', 'Cannot delete your own account.');
        }

        \App\Services\ActivityLogService::log('staff_deleted', $staff, ['name' => $staff->name]);
        $staff->delete();

        return redirect()->route('admin.staff')->with('success', 'Staff member deleted.');
    })->name('staff.destroy');

    // ─── Activity Logs ─── admin + super_admin
    Route::get('/activity-logs', function () {
        if (!in_array(Auth::user()->role, ['super_admin', 'admin'])) abort(403);

        $query = \App\Models\ActivityLog::with('user')->latest();

        if ($action = request('action')) {
            $query->where('action', $action);
        }

        if ($userId = request('user_id')) {
            $query->where('user_id', $userId);
        }

        $logs = $query->paginate(50)->withQueryString();
        $actions = \App\Models\ActivityLog::distinct()->pluck('action');
        $users = User::whereIn('id', \App\Models\ActivityLog::distinct()->pluck('user_id'))->get(['id', 'name']);

        return view('admin.activity-logs', compact('logs', 'actions', 'users'));
    })->name('activity-logs');
});
