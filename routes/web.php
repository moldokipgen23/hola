<?php

use App\Http\Controllers\Api\AiAgentController;
use App\Models\ActivityLog;
use App\Models\AgentImportedBusiness;
use App\Models\AiAgent;
use App\Models\Area;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Category;
use App\Models\ClaimRequest;
use App\Models\ClaimVerification;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\Review;
use App\Models\SearchHistory;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Subcategory;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// Public Routes
Route::get('/', function () {
    return view('public.home');
})->name('home');

Route::get('/businesses', function () {
    $query = Business::where('is_active', true)->inServiceableArea()->with('category', 'area');

    if ($search = request('q') ?: request('search')) {
        $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
        $query->where(function ($q) use ($safe) {
            $q->where('name', 'like', $safe)
                ->orWhere('description', 'like', $safe)
                ->orWhere('address', 'like', $safe);
        });
    }

    if ($category = request('category')) {
        $query->whereHas('category', fn ($q) => $q->where('slug', $category));
    }

    if ($area = request('area')) {
        $query->whereHas('area', fn ($q) => $q->where('slug', $area));
    }

    $sort = request('sort', 'latest');
    $query = match ($sort) {
        'rating' => $query->orderByDesc('average_rating'),
        'name' => $query->orderBy('name'),
        default => $query->latest(),
    };

    $businesses = $query->paginate(12)->withQueryString();

    return view('public.businesses', compact('businesses'));
})->name('public.businesses');

Route::get('/explore', function () {
    $query = Business::where('is_active', true)->inServiceableArea()->with('category', 'area');

    if ($module = request('module')) {
        $module = in_array($module, ['ordering', 'booking', 'directory']) ? $module : null;
        $query->whereHas('category', fn ($q) => $q->whereIn('module_type', [$module, 'both']));
    }

    if ($search = request('q')) {
        $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
        $query->where(function ($q) use ($safe) {
            $q->where('name', 'like', $safe)
                ->orWhere('description', 'like', $safe)
                ->orWhere('address', 'like', $safe);
        });
    }

    if ($category = request('category')) {
        $query->whereHas('category', fn ($q) => $q->where('slug', $category));
    }

    if ($area = request('area')) {
        $query->whereHas('area', fn ($q) => $q->where('slug', $area));
    }

    $sort = request('sort', 'latest');
    $query = match ($sort) {
        'rating' => $query->orderByDesc('average_rating'),
        'name' => $query->orderBy('name'),
        default => $query->latest(),
    };

    $businesses = $query->paginate(12)->withQueryString();

    return view('public.explore', compact('businesses'));
})->name('explore');

Route::get('/categories', function () {
    return view('public.categories');
})->name('public.categories');

Route::get('/areas', function () {
    $areas = Area::active()->where('slug', '!=', 'other')->withCount('businesses')->orderByDesc('businesses_count')->get();

    return view('public.areas', compact('areas'));
})->name('public.areas');

Route::get('/area/{slug}', function ($slug) {
    $area = Area::where('slug', $slug)->firstOrFail();
    $query = $area->businesses()->active()->inServiceableArea()->with('category');

    if ($category = request('category')) {
        $query->whereHas('category', fn ($q) => $q->where('slug', $category));
    }

    $businesses = $query->latest()->paginate(12)->withQueryString();

    return view('public.area', compact('area', 'businesses'));
})->name('public.area');

Route::get('/map', function () {
    $businesses = Business::where('is_active', true)
        ->inServiceableArea()
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->with('category')
        ->get();

    return view('public.map', compact('businesses'));
})->name('public.map');

Route::get('/category/{slug}', function ($slug) {
    $category = Category::where('slug', $slug)->firstOrFail();
    $businesses = $category->businesses()->where('is_active', true)->inServiceableArea()->latest()->paginate(12);

    return view('public.category', compact('category', 'businesses'));
})->name('public.category');

Route::get('/business/{slug}', function ($slug) {
    $business = Business::where('slug', $slug)
        ->where('is_active', true)
        ->inServiceableArea()
        ->with(['category', 'products', 'reviews' => fn ($q) => $q->with('user:id,name')->latest()])
        ->firstOrFail();

    return view('public.business', compact('business'));
})->name('public.business');

Route::get('/claim/{id}', function ($id) {
    $business = Business::withoutTrashed()->findOrFail($id);

    return view('public.claim', compact('business'));
})->name('public.claim');

Route::post('/claim/{id}/send-otp', function ($id) {
    $business = Business::withoutTrashed()->findOrFail($id);
    $request = request()->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255',
        'phone' => 'required|string|max:20',
        'relation' => 'required|string|max:100',
        'message' => 'nullable|string|max:1000',
    ]);

    $otp = ClaimVerification::generateOtp();
    $expiresAt = now()->addMinutes(10);

    $normalizedPhone = str_replace([' ', '-', '(', ')'], '', $request['phone']);
    if (! str_starts_with($normalizedPhone, '+')) {
        $normalizedPhone = '+91'.$normalizedPhone;
    }

    ClaimVerification::create([
        'business_id' => $id,
        'phone' => $normalizedPhone,
        'email' => $request['email'],
        'otp' => $otp,
        'channel' => 'whatsapp',
        'expires_at' => $expiresAt,
    ]);

    session([
        'claim_data' => [
            'business_id' => $id,
            'name' => $request['name'],
            'email' => $request['email'],
            'phone' => $normalizedPhone,
            'relation' => $request['relation'],
            'message' => $request['message'] ?? '',
        ],
        'claim_otp_id' => null,
    ]);

    // Store otp_id in session for verification
    $verification = ClaimVerification::where('business_id', $id)
        ->where('email', $request['email'])
        ->latest()
        ->first();
    session(['claim_otp_id' => $verification->id]);

    // Send via WhatsApp (CallMeBot)
    $sent = false;
    try {
        $message = "Your Hola verification code is: *{$otp}\n\nThis code expires in 10 minutes.\nDo not share this code with anyone.";
        $response = Http::timeout(10)
            ->post('https://api.callmebot.com/whatsapp.php', [
                'phone' => $normalizedPhone,
                'text' => $message,
                'apikey' => config('services.callmebot.api_key', ''),
            ]);
        $sent = $response->successful();
    } catch (Exception $e) {
        $sent = false;
    }

    // Fallback: send OTP via email
    if (! $sent && ! empty($request['email'])) {
        try {
            Mail::raw(
                "Your Hola verification code is: {$otp}\n\nThis code expires in 10 minutes.",
                function ($mail) use ($request) {
                    $mail->to($request['email'])
                        ->subject('Hola — Verification Code')
                        ->from('noreply@hola.ehlom.com', 'Hola');
                }
            );
            $sent = true;
            // Update channel to email
            $verification->update(['channel' => 'email']);
        } catch (Exception $e) {
            $sent = false;
        }
    }

    if ($sent) {
        return redirect()->route('public.claim.verify', $id)
            ->with('success', 'Verification code sent to your '.($verification->channel === 'whatsapp' ? 'WhatsApp' : 'email').'.');
    }

    return back()->with('error', 'Failed to send verification code. Please try again.');
})->name('public.claim.send-otp');

Route::get('/claim/{id}/verify', function ($id) {
    $business = Business::withoutTrashed()->findOrFail($id);
    $claimData = session('claim_data');

    if (! $claimData || $claimData['business_id'] != $id) {
        return redirect()->route('public.claim', $id)
            ->with('error', 'Session expired. Please start again.');
    }

    return view('public.claim-verify', compact('business'));
})->name('public.claim.verify');

Route::post('/claim/{id}/verify', function ($id) {
    $business = Business::withoutTrashed()->findOrFail($id);
    $claimData = session('claim_data');
    $otpId = session('claim_otp_id');

    if (! $claimData || $claimData['business_id'] != $id || ! $otpId) {
        return redirect()->route('public.claim', $id)
            ->with('error', 'Session expired. Please start again.');
    }

    $request = request()->validate([
        'otp' => 'required|string|size:6',
    ]);

    $verification = ClaimVerification::findOrFail($otpId);

    if ($verification->isExpired()) {
        return back()->with('error', 'Code expired. Please request a new one.');
    }

    if (! $verification->verify($request['otp'])) {
        return back()->with('error', 'Invalid code. Please try again.');
    }

    // Create user if not exists
    $user = User::where('email', $claimData['email'])->first();
    if (! $user) {
        $user = User::create([
            'name' => $claimData['name'],
            'email' => $claimData['email'],
            'phone' => $claimData['phone'],
            'password' => bcrypt('password'),
            'role' => 'customer',
        ]);
    }

    $existingClaim = ClaimRequest::where('business_id', $id)
        ->where('user_id', $user->id)
        ->where('status', 'pending')
        ->first();

    if ($existingClaim) {
        return redirect()->route('public.business', $business->slug)
            ->with('error', 'You already have a pending claim for this business.');
    }

    ClaimRequest::create([
        'business_id' => $id,
        'user_id' => $user->id,
        'status' => 'pending',
        'notes' => "Relation: {$claimData['relation']}. Verified via OTP. ".($claimData['message'] ?? ''),
    ]);

    session()->forget(['claim_data', 'claim_otp_id']);

    return redirect()->route('public.business', $business->slug)
        ->with('success', 'Identity verified! Claim submitted. We will review it within 24 hours.');
})->name('public.claim.verify.submit');

Route::post('/claim/{id}/resend-otp', function ($id) {
    $verification = ClaimVerification::where('business_id', $id)
        ->latest()
        ->first();

    if (! $verification) {
        return back()->with('error', 'No verification found. Please start again.');
    }

    $otp = ClaimVerification::generateOtp();
    $verification->update([
        'otp' => $otp,
        'expires_at' => now()->addMinutes(10),
        'verified' => false,
    ]);

    $sent = false;
    if ($verification->channel === 'whatsapp') {
        try {
            $message = "Your Hola verification code is: *{$otp}\n\nThis code expires in 10 minutes.";
            $response = Http::timeout(10)
                ->post('https://api.callmebot.com/whatsapp.php', [
                    'phone' => $verification->phone,
                    'text' => $message,
                    'apikey' => config('services.callmebot.api_key', ''),
                ]);
            $sent = $response->successful();
        } catch (Exception $e) {
            $sent = false;
        }
    }

    if (! $sent) {
        try {
            Mail::raw(
                "Your Hola verification code is: {$otp}\n\nThis code expires in 10 minutes.",
                function ($mail) use ($verification) {
                    $mail->to($verification->email)
                        ->subject('Hola — Verification Code')
                        ->from('noreply@hola.ehlom.com', 'Hola');
                }
            );
            $sent = true;
            $verification->update(['channel' => 'email']);
        } catch (Exception $e) {
            $sent = false;
        }
    }

    if ($sent) {
        return back()->with('success', 'New code sent.');
    }

    return back()->with('error', 'Failed to resend code.');
})->name('public.claim.resend-otp');

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
    $businesses = Business::where('is_active', true)->select('slug', 'updated_at')->get();
    $categories = Category::select('slug')->get();

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

    if (! $user || ! Hash::check($request->password, $user->password)) {
        return back()->withErrors(['email' => 'Invalid credentials.']);
    }

    if ($user->banned_at) {
        return back()->withErrors(['email' => 'Your account has been suspended.']);
    }

    if (property_exists($user, 'is_active') && ! $user->is_active) {
        return back()->withErrors(['email' => 'Your account is inactive.']);
    }

    Auth::login($user);
    $request->session()->regenerate();

    $user->recordLogin();
    ActivityLogService::log('admin_login', $user);

    return redirect()->route('admin.dashboard');
})->middleware('throttle:5,1')->name('admin.login.post');

Route::post('/admin/logout', function () {
    ActivityLogService::log('admin_logout');
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
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
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

        ActivityLogService::log('user_created', $user, ['role' => $user->role]);

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
            'email' => 'nullable|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|unique:users,phone,'.$user->id,
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
        ActivityLogService::log('user_updated', $user);

        return redirect()->route('admin.users.show', $user->id)->with('success', 'User updated.');
    })->name('users.update');

    Route::delete('/users/{id}', function ($id) {
        $user = User::findOrFail($id);

        if ($user->isSuperAdmin()) {
            return back()->with('error', 'Cannot delete super admin.');
        }

        $user->delete();
        ActivityLogService::log('user_deleted', $user, ['name' => $user->name]);

        return redirect()->route('admin.users')->with('success', 'User deleted.');
    })->name('users.destroy');

    Route::post('/users/{id}/ban', function (Request $request, $id) {
        $user = User::findOrFail($id);
        $user->ban($request->input('reason'));
        ActivityLogService::log('user_banned', $user, ['reason' => $request->input('reason')]);

        return back()->with('success', 'User banned.');
    })->name('users.ban');

    Route::post('/users/{id}/unban', function ($id) {
        $user = User::findOrFail($id);
        $user->unban();
        ActivityLogService::log('user_unbanned', $user);

        return back()->with('success', 'User unbanned.');
    })->name('users.unban');

    Route::post('/users/bulk', function (Request $request) {
        $action = $request->input('action');
        $ids = json_decode($request->input('ids', '[]'), true);

        if (empty($ids)) {
            return back()->with('error', 'No users selected.');
        }

        $users = User::whereIn('id', $ids)->get();
        $count = 0;

        foreach ($users as $user) {
            if ($user->isSuperAdmin()) {
                continue;
            }

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
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
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
            'pincode' => 'required|string|size:6',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'delivery_radius_km' => 'nullable|numeric|min:1|max:100',
        ]);

        // Validate pincode
        $pincode = App\Models\Pincode::lookup($validated['pincode']);
        if (! $pincode) {
            return back()->withErrors(['pincode' => 'Invalid pincode.'])->withInput();
        }
        if (! $pincode->serviceable) {
            return back()->withErrors(['pincode' => "{$pincode->district}, {$pincode->state} is not yet serviceable."])->withInput();
        }

        $validated['slug'] = $request->slug ?: Str::slug($request->name);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['district'] = $pincode->district;
        $validated['state'] = $pincode->state;
        $validated['created_by'] = Auth::id();
        $validated['payment_methods'] = collect($request->input('payment_methods', []))->filter(fn($v) => $v)->values()->toArray();

        Business::create($validated);
        ActivityLogService::log('business_created', null, ['name' => $validated['name']]);

        return redirect()->route('admin.businesses')->with('success', 'Business created.');
    })->name('businesses.store');

    Route::get('/businesses/{id}', function ($id) {
        $business = Business::with(['category', 'subcategory', 'products', 'reviews.user', 'user', 'createdBy', 'deliveryZones.area'])->findOrFail($id);

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
            'pincode' => 'sometimes|required|string|size:6',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'delivery_radius_km' => 'nullable|numeric|min:1|max:100',
        ]);

        // Handle pincode update
        if ($request->has('pincode')) {
            $pincode = App\Models\Pincode::lookup($validated['pincode']);
            if (! $pincode) {
                return back()->withErrors(['pincode' => 'Invalid pincode.'])->withInput();
            }
            if (! $pincode->serviceable) {
                return back()->withErrors(['pincode' => "{$pincode->district}, {$pincode->state} is not yet serviceable."])->withInput();
            }
            $validated['state'] = $pincode->state;
            $validated['district'] = $pincode->district;
        }

        $validated['slug'] = $request->slug ?: Str::slug($request->name);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['payment_methods'] = collect($request->input('payment_methods', []))->filter(fn($v) => $v)->values()->toArray();

        $business->update($validated);
        ActivityLogService::log('business_updated', $business);

        return redirect()->route('admin.businesses')->with('success', 'Business updated.');
    })->name('businesses.update');

    Route::delete('/businesses/{id}', function ($id) {
        Business::findOrFail($id)->delete();
        ActivityLogService::log('business_deleted', null, ['id' => $id]);

        return redirect()->route('admin.businesses')->with('success', 'Business deleted.');
    })->name('businesses.destroy');

    // Bulk business actions
    Route::post('/businesses/bulk', function (Request $request) {
        $action = $request->input('action');
        $ids = json_decode($request->input('ids', '[]'), true);

        if (empty($ids)) {
            return back()->with('error', 'No items selected.');
        }

        switch ($action) {
            case 'activate':
                Business::whereIn('id', $ids)->update(['is_active' => true]);

                return back()->with('success', count($ids).' businesses activated.');
            case 'deactivate':
                Business::whereIn('id', $ids)->update(['is_active' => false]);

                return back()->with('success', count($ids).' businesses deactivated.');
            case 'feature':
                Business::whereIn('id', $ids)->update(['is_featured' => true]);

                return back()->with('success', count($ids).' businesses featured.');
            case 'unfeature':
                Business::whereIn('id', $ids)->update(['is_featured' => false]);

                return back()->with('success', count($ids).' businesses unfeatured.');
            case 'delete':
                Business::whereIn('id', $ids)->delete();

                return back()->with('success', count($ids).' businesses deleted.');
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
            'module_type' => 'required|in:directory,ordering,booking,both',
        ]);

        $validated['slug'] = $request->slug ?: Str::slug($request->name);
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
            'module_type' => 'required|in:directory,ordering,booking,both',
        ]);

        $validated['slug'] = $request->slug ?: Str::slug($request->name);
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
        $validated['slug'] = $request->slug ?: Str::slug($request->name);
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
        $validated['slug'] = $request->slug ?: Str::slug($request->name);
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
        $validated['slug'] = $request->slug ?: Str::slug($request->name);
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
        $validated['slug'] = $request->slug ?: Str::slug($request->name);
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
        NotificationService::claimApproved($claim);
        ActivityLogService::log('claim_approved', $claim, ['business_id' => $claim->business_id, 'user_id' => $claim->user_id]);

        return redirect()->route('admin.claims')->with('success', 'Claim approved. User upgraded to owner.');
    })->name('claims.approve');

    Route::patch('/claims/{id}/reject', function ($id) {
        $claim = ClaimRequest::with(['user', 'business'])->findOrFail($id);
        $claim->update(['status' => 'rejected']);
        NotificationService::claimRejected($claim);
        ActivityLogService::log('claim_rejected', $claim, ['business_id' => $claim->business_id]);

        return redirect()->route('admin.claims')->with('success', 'Claim rejected.');
    })->name('claims.reject');

    // Bulk claim actions
    Route::post('/claims/bulk-approve', function (Request $request) {
        $ids = json_decode($request->input('ids', '[]'), true);
        if (empty($ids)) {
            return back()->with('error', 'No items selected.');
        }

        foreach ($ids as $id) {
            $claim = ClaimRequest::with(['user', 'business'])->findOrFail($id);
            $claim->update(['status' => 'approved']);
            $claim->business->update(['claim_status' => 'claimed', 'created_by' => $claim->user_id]);
            if ($claim->user->role === 'customer') {
                $claim->user->update(['role' => 'owner']);
            }
            NotificationService::claimApproved($claim);
            ActivityLogService::log('claim_approved', $claim, ['business_id' => $claim->business_id, 'user_id' => $claim->user_id]);
        }

        return back()->with('success', count($ids).' claims approved. Users upgraded to owner.');
    })->name('claims.bulk-approve');

    Route::post('/claims/bulk-reject', function (Request $request) {
        $ids = json_decode($request->input('ids', '[]'), true);
        if (empty($ids)) {
            return back()->with('error', 'No items selected.');
        }

        foreach ($ids as $id) {
            $claim = ClaimRequest::with('business')->findOrFail($id);
            $claim->update(['status' => 'rejected']);
            NotificationService::claimRejected($claim);
            ActivityLogService::log('claim_rejected', $claim, ['business_id' => $claim->business_id]);
        }

        return back()->with('success', count($ids).' claims rejected.');
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
    Route::post('/reports/bulk-resolve', function (Request $request) {
        $ids = json_decode($request->input('ids', '[]'), true);
        if (empty($ids)) {
            return back()->with('error', 'No items selected.');
        }

        Report::whereIn('id', $ids)->update(['status' => 'resolved']);

        return back()->with('success', count($ids).' reports resolved.');
    })->name('reports.bulk-resolve');

    // Settings
    Route::get('/settings', function () {
        $all = Setting::orderBy('key')->get()->pluck('value', 'key')->toArray();

        return view('admin.settings.index', ['settings' => $all]);
    })->name('settings');

    Route::put('/settings', function (Request $request) {
        $settings = $request->input('settings', []);
        foreach ($settings as $key => $value) {
            Setting::set($key, $value, str_starts_with($key, 'smtp') ? 'smtp' : (str_starts_with($key, 'api_key') ? 'api' : 'general'));
        }

        return redirect()->route('admin.settings')->with('success', 'Settings saved.');
    })->name('settings.update');

    Route::post('/settings/test-email', function (Request $request) {
        $request->validate(['email' => 'required|email']);

        try {
            Mail::raw(
                "Hola SMTP Test\n\nThis is a test email from your Hola app.\n\nIf you received this, your SMTP configuration is working correctly!\n\nSent at: ".now()->format('Y-m-d H:i:s'),
                function ($message) use ($request) {
                    $message->to($request->email)
                        ->subject('Hola - SMTP Test Email')
                        ->from(config('mail.from.address', 'noreply@hola.app'), config('mail.from.name', 'Hola'));
                }
            );

            return response()->json(['message' => 'Test email sent successfully! Check your inbox.']);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to send: '.$e->getMessage()], 500);
        }
    })->name('settings.test-email');

    Route::post('/settings/test-telegram', function (Request $request) {
        $request->validate(['message' => 'required|string']);

        $token = Setting::get('telegram_bot_token');
        $chatId = Setting::get('telegram_chat_id');

        if (! $token || ! $chatId) {
            return response()->json(['message' => 'Telegram bot token and chat ID not configured.'], 422);
        }

        try {
            $response = Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $request->message,
                'parse_mode' => 'HTML',
            ]);

            if ($response->successful()) {
                return response()->json(['message' => 'Test Telegram message sent successfully!']);
            }

            return response()->json(['message' => 'Failed: '.($response->json('description') ?? 'Unknown error')], 500);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed: '.$e->getMessage()], 500);
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
            'total_reviews' => Review::count(),
            'total_claims' => ClaimRequest::count(),
            'pending_claims' => ClaimRequest::where('status', 'pending')->count(),
            'pending_reports' => Report::where('status', 'pending')->count(),
            'pending_imports' => ImportItem::where('status', 'pending')->count(),
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
    $agentCtrl = AiAgentController::class;

    Route::get('/autopilot', function () {
        $agent = AiAgent::first();
        $recentTasks = $agent ? $agent->tasks()->latest()->take(20)->get() : collect();
        $pendingImports = ImportItem::where('status', 'pending')->count();
        $totalBusinesses = Business::where('is_active', true)->count();
        $totalCategories = Category::count();
        $todaysTasks = $agent ? $agent->tasks()->where('created_at', '>=', now()->startOfDay())->count() : 0;
        $todaysImports = $agent ? $agent->tasks()->where('created_at', '>=', now()->startOfDay())->sum('imported_count') : 0;
        $lastRun = $agent ? $agent->tasks()->latest()->first() : null;
        $nextRun = now()->addMinutes(240 - (now()->timestamp % 240));

        return view('admin.autopilot', compact(
            'agent', 'recentTasks', 'pendingImports', 'totalBusinesses',
            'totalCategories', 'todaysTasks', 'todaysImports', 'lastRun', 'nextRun'
        ));
    })->name('autopilot');

    Route::post('/autopilot/toggle', function (Request $request) {
        $agent = AiAgent::first();
        if (! $agent) {
            return back()->with('error', 'No agent found.');
        }

        $agent->update(['status' => $agent->status === 'active' ? 'paused' : 'active']);

        ActivityLog::create([
            'action' => 'autopilot_toggled',
            'user_id' => auth()->id(),
            'properties' => ['new_status' => $agent->status],
        ]);

        return back()->with('success', "Autopilot {$agent->status}.");
    })->name('autopilot.toggle');

    Route::post('/autopilot/prompt', function (Request $request) {
        $request->validate([
            'system_prompt' => 'required|string|max:5000',
        ]);

        $agent = AiAgent::first();
        if (! $agent) {
            return back()->with('error', 'No agent found.');
        }

        $agent->update(['system_prompt' => $request->system_prompt]);

        return back()->with('success', 'Agent rules updated.');
    })->name('autopilot.prompt');

    Route::post('/autopilot/location', function (Request $request) {
        $request->validate([
            'search_district' => 'required|string|max:255',
            'search_state' => 'required|string|max:255',
            'search_zipcodes' => 'required|string|max:500',
            'search_areas' => 'required|string|max:500',
        ]);

        Setting::set('search_district', $request->search_district, 'search');
        Setting::set('search_state', $request->search_state, 'search');
        Setting::set('search_zipcodes', $request->search_zipcodes, 'search');
        Setting::set('search_areas', $request->search_areas, 'search');

        return back()->with('success', 'Search locations updated. Agent will use these on next run.');
    })->name('autopilot.location');

    Route::get('/agents', function () use ($agentCtrl) {
        $response = (new $agentCtrl)->index();
        $agents = json_decode($response->getContent(), true)['agents'];

        return view('admin.agents.index', compact('agents'));
    })->name('agents');

    Route::get('/agents/create', function () {
        return view('admin.agents.create');
    })->name('agents.create');

    Route::post('/agents', function (Request $request) use ($agentCtrl) {
        $response = (new $agentCtrl)->store($request);

        return redirect()->route('admin.agents')->with('success', 'Agent created.');
    })->name('agents.store');

    Route::get('/agents/{id}', function ($id) use ($agentCtrl) {
        $response = (new $agentCtrl)->show($id);
        $data = json_decode($response->getContent(), true);
        $agent = $data['agent'];
        $recentTasks = $data['recent_tasks'];

        return view('admin.agents.show', compact('agent', 'recentTasks'));
    })->name('agents.show');

    Route::get('/agents/{id}/edit', function ($id) {
        $agent = AiAgent::findOrFail($id);

        return view('admin.agents.edit', compact('agent'));
    })->name('agents.edit');

    Route::put('/agents/{id}', function ($id, Request $request) use ($agentCtrl) {
        $response = (new $agentCtrl)->update($request, $id);

        return redirect()->route('admin.agents.show', $id)->with('success', 'Agent updated.');
    })->name('agents.update');

    Route::delete('/agents/{id}', function ($id) use ($agentCtrl) {
        $response = (new $agentCtrl)->destroy($id);

        return redirect()->route('admin.agents')->with('success', 'Agent deleted.');
    })->name('agents.destroy');

    Route::post('/agents/{id}/run', function ($id, Request $request) use ($agentCtrl) {
        $response = (new $agentCtrl)->runTask($request, $id);
        $data = json_decode($response->getContent(), true);

        return back()->with('success', "Task completed. Imported {$data['result']['imported']} items.");
    })->name('agents.run');

    // ─── Import ───

    Route::get('/search-history', function () {
        $history = SearchHistory::with('agent:id,name,avatar')
            ->latest()
            ->paginate(20);

        return view('admin.search-history', compact('history'));
    })->name('search-history');

    Route::get('/import', function () {
        $batches = ImportBatch::withCount('items')
            ->with('agent:id,name,avatar')
            ->latest()
            ->paginate(20);

        return view('admin.import.index', compact('batches'));
    })->name('import');

    Route::get('/import/review', function () {
        $status = request('status', 'pending');
        if ($status === 'duplicates') {
            $query = ImportItem::where('status', 'duplicate')->with('batch:id,name,source');
        } elseif ($status === 'all') {
            $query = ImportItem::whereIn('status', ['pending', 'duplicate'])->with('batch:id,name,source');
        } else {
            $query = ImportItem::where('status', 'pending')->with('batch:id,name,source');
        }
        if (request('batch_id')) {
            $query->where('batch_id', request('batch_id'));
        }
        $items = $query->latest()->paginate(20);

        return view('admin.import.review', compact('items', 'status'));
    })->name('import.review');

    Route::post('/import/review/{id}/approve', function ($id) {
        $item = ImportItem::with('batch')->findOrFail($id);
        $data = $item->data;

        // DUPLICATE CHECK
        $existingBusiness = null;

        // Check by external_id
        if (! empty($item->external_id)) {
            $existingBusiness = Business::withoutTrashed()->where('external_id', $item->external_id)->first();
        }

        // Check by name + address
        if (! $existingBusiness && ! empty($data['name'])) {
            $existingBusiness = Business::withoutTrashed()->whereRaw('LOWER(name) = ?', [strtolower(trim($data['name'], " \t\n\r\0\x0B,"))])->first();
            if ($existingBusiness && ! empty($data['address'])) {
                similar_text(strtolower($existingBusiness->address), strtolower($data['address']), $percent);
                if ($percent < 50) {
                    $existingBusiness = null;
                }
            }
        }

        // Check by phone
        if (! $existingBusiness && ! empty($data['phone'])) {
            $normalizedPhone = str_replace([' ', '-', '(', ')', '+'], '', $data['phone']);
            $existingBusiness = Business::withoutTrashed()->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') = ?", [$normalizedPhone])->first();
        }

        if ($existingBusiness) {
            $item->update(['status' => 'rejected', 'notes' => "Duplicate: {$existingBusiness->name}"]);
            if ($item->batch) {
                $item->batch->increment('rejected');
                $item->batch->decrement('pending');
            }

            return back()->with('error', "Skipped: Business already exists ({$existingBusiness->name})");
        }

        $categories = Category::pluck('id', 'name')->toArray();

        $categoryName = $data['category'] ?? $data['type'] ?? null;
        $categoryId = matchImportCategory($categoryName, $categories);
        $subcategoryId = matchImportSubcategory($categoryName, $data['name'] ?? null, $categoryId);

        // Detect area
        $areaId = $data['area_id'] ?? null;
        if (! $areaId && ! empty($data['latitude']) && ! empty($data['longitude'])) {
            $area = Area::findByCoordinates($data['latitude'], $data['longitude']);
            if ($area) {
                $areaId = $area->id;
            }
        }
        if (! $areaId) {
            $otherArea = Area::where('slug', 'other')->where('is_active', true)->first();
            if ($otherArea) {
                $areaId = $otherArea->id;
            }
        }

        $slug = Str::slug(trim($data['name'] ?? 'unknown-business', " \t\n\r\0\x0B,"));
        $existing = Business::withTrashed()->where('slug', $slug)->first();
        if ($existing) {
            $slug .= '-'.Str::random(5);
        }

        Business::create([
            'name' => $data['name'] ?? 'Unknown Business',
            'slug' => $slug,
            'category_id' => $categoryId,
            'subcategory_id' => $subcategoryId,
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
            'photos' => ! empty($data['photos']) && is_array($data['photos']) ? $data['photos'] : null,
        ]);

        $newBusiness = Business::where('slug', $slug)->first();
        if ($newBusiness && $item->batch && $item->batch->agent_id) {
            try {
                AgentImportedBusiness::create([
                    'agent_id' => $item->batch->agent_id,
                    'business_id' => $newBusiness->id,
                    'batch_id' => $item->batch_id,
                    'google_place_id' => $item->external_id,
                    'business_name' => $data['name'] ?? 'Unknown Business',
                    'address' => $data['address'] ?? null,
                    'imported_at' => now(),
                ]);
            } catch (Exception $e) { /* memory failure should not block approve */
            }
        }

        $item->update(['status' => 'approved']);
        if ($item->batch) {
            $item->batch->increment('approved');
            $item->batch->decrement('pending');
        }

        return back()->with('success', 'Business created from import.');
    })->name('import.approve');

    Route::post('/import/review/{id}/reject', function ($id) {
        $item = ImportItem::findOrFail($id);
        $item->update(['status' => 'rejected']);
        if ($item->batch) {
            $item->batch->increment('rejected');
            $item->batch->decrement('pending');
        }

        return back()->with('success', 'Item rejected.');
    })->name('import.reject');

    // Bulk import actions
    Route::post('/import/bulk-approve', function (Request $request) {
        set_time_limit(120);
        $ids = json_decode($request->input('ids', '[]'), true);
        if (empty($ids)) {
            return back()->with('error', 'No items selected.');
        }

        $approved = 0;
        $skipped = 0;
        foreach ($ids as $id) {
            try {
                $item = ImportItem::with('batch')->findOrFail($id);
                $data = $item->data;

                // DUPLICATE CHECK
                $existingBusiness = null;

                // Check by external_id
                if (! empty($item->external_id)) {
                    $existingBusiness = Business::withoutTrashed()->where('external_id', $item->external_id)->first();
                }

                // Check by name + address
                if (! $existingBusiness && ! empty($data['name'])) {
                    $existingBusiness = Business::withoutTrashed()->whereRaw('LOWER(name) = ?', [strtolower(trim($data['name'], " \t\n\r\0\x0B,"))])->first();
                    if ($existingBusiness && ! empty($data['address'])) {
                        similar_text(strtolower($existingBusiness->address), strtolower($data['address']), $percent);
                        if ($percent < 50) {
                            $existingBusiness = null;
                        }
                    }
                }

                // Check by phone
                if (! $existingBusiness && ! empty($data['phone'])) {
                    $normalizedPhone = str_replace([' ', '-', '(', ')', '+'], '', $data['phone']);
                    $existingBusiness = Business::withoutTrashed()->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', '') = ?", [$normalizedPhone])->first();
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

                $categories = Category::pluck('id', 'name')->toArray();
                $categoryName = $data['category'] ?? $data['type'] ?? null;
                $categoryId = matchImportCategory($categoryName, $categories);

                // Detect area
                $areaId = $data['area_id'] ?? null;
                if (! $areaId && ! empty($data['latitude']) && ! empty($data['longitude'])) {
                    $area = Area::findByCoordinates($data['latitude'], $data['longitude']);
                    if ($area) {
                        $areaId = $area->id;
                    }
                }
                if (! $areaId) {
                    $otherArea = Area::where('slug', 'other')->where('is_active', true)->first();
                    if ($otherArea) {
                        $areaId = $otherArea->id;
                    }
                }

                $slug = Str::slug(trim($data['name'] ?? 'unknown-business', " \t\n\r\0\x0B,"));
                $existing = Business::withTrashed()->where('slug', $slug)->first();
                if ($existing) {
                    $slug .= '-'.Str::random(5);
                }

                Business::create([
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
                    'photos' => ! empty($data['photos']) && is_array($data['photos']) ? $data['photos'] : null,
                ]);

                $newBusiness = Business::where('slug', $slug)->first();
                if ($newBusiness && $item->batch && $item->batch->agent_id) {
                    AgentImportedBusiness::create([
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
            } catch (Exception $e) {
                continue;
            }
        }
        $message = "Approved {$approved} items.";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} duplicates.";
        }

        if ($approved > 0) {
            Artisan::call('photos:download', ['--limit' => $approved]);
        }

        return back()->with('success', $message);
    })->name('import.bulk-approve');

    Route::post('/import/bulk-reject', function (Request $request) {
        $ids = json_decode($request->input('ids', '[]'), true);
        if (empty($ids)) {
            return back()->with('error', 'No items selected.');
        }

        foreach ($ids as $id) {
            $item = ImportItem::findOrFail($id);
            $item->update(['status' => 'rejected']);
            if ($item->batch) {
                $item->batch->increment('rejected');
                $item->batch->decrement('pending');
            }
        }

        return back()->with('success', count($ids).' items rejected.');
    })->name('import.bulk-reject');

    Route::post('/import/bulk-delete-duplicates', function () {
        $deleted = ImportItem::where('status', 'duplicate')->delete();

        return back()->with('success', "Deleted {$deleted} duplicate items.");
    })->name('import.bulk-delete-duplicates');

    Route::post('/import/approve-all', function () {
        set_time_limit(300);
        $items = ImportItem::where('status', 'pending')->with('batch')->get();
        $approved = 0;
        $skipped = 0;

        // Pre-load categories and existing businesses for fast matching
        $categories = Category::pluck('id', 'name')->toArray();
        $existingPlaceIds = Business::withoutTrashed()->whereNotNull('external_id')->pluck('external_id')->toArray();
        $existingNames = Business::withoutTrashed()->pluck('name')->map(fn ($n) => strtolower($n))->toArray();

        foreach ($items as $item) {
            try {
                $data = $item->data;
                $placeId = $item->external_id;
                $name = strtolower(trim($data['name'] ?? ''));
                $cleanName = preg_replace('/\s*[-–,]\s*(churachandpur|lamka|manipur|india).*$/i', '', $name);

                // Fast duplicate check using pre-loaded arrays
                $isDuplicate = false;
                if ($placeId && in_array($placeId, $existingPlaceIds)) {
                    $isDuplicate = true;
                }
                if (! $isDuplicate && (in_array($name, $existingNames) || in_array($cleanName, $existingNames))) {
                    $isDuplicate = true;
                }

                if ($isDuplicate) {
                    $item->update(['status' => 'rejected', 'notes' => 'Duplicate detected during approve-all']);
                    if ($item->batch) {
                        $item->batch->increment('rejected');
                        $item->batch->decrement('pending');
                    }
                    $skipped++;

                    continue;
                }

                $categoryName = $data['category'] ?? $data['type'] ?? null;
                $categoryId = matchImportCategory($categoryName, $categories);
                $subcategoryId = matchImportSubcategory($categoryName, $data['name'] ?? null, $categoryId);

                $areaId = $data['area_id'] ?? null;
                if (! $areaId && ! empty($data['latitude']) && ! empty($data['longitude'])) {
                    $area = Area::findByCoordinates($data['latitude'], $data['longitude']);
                    if ($area) {
                        $areaId = $area->id;
                    }
                }
                if (! $areaId) {
                    $otherArea = Area::where('slug', 'other')->where('is_active', true)->first();
                    if ($otherArea) {
                        $areaId = $otherArea->id;
                    }
                }

                $slug = Str::slug(trim($data['name'] ?? 'unknown-business'));
                if (Business::withTrashed()->where('slug', $slug)->exists()) {
                    $slug .= '-'.Str::random(5);
                }

                Business::create([
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
                    'photos' => ! empty($data['photos']) && is_array($data['photos']) ? $data['photos'] : null,
                ]);

                $newBusiness = Business::where('slug', $slug)->first();
                if ($newBusiness && $item->batch && $item->batch->agent_id) {
                    AgentImportedBusiness::create([
                        'agent_id' => $item->batch->agent_id,
                        'business_id' => $newBusiness->id,
                        'batch_id' => $item->batch_id,
                        'google_place_id' => $item->external_id,
                        'business_name' => $data['name'] ?? 'Unknown Business',
                        'address' => $data['address'] ?? null,
                        'imported_at' => now(),
                    ]);
                }

                // Add to pre-loaded arrays to prevent duplicates within this batch
                if ($placeId) {
                    $existingPlaceIds[] = $placeId;
                }
                $existingNames[] = $name;

                $item->update(['status' => 'approved']);
                if ($item->batch) {
                    $item->batch->increment('approved');
                    $item->batch->decrement('pending');
                }
                $approved++;
            } catch (Exception $e) {
                continue;
            }
        }

        $remaining = ImportItem::where('status', 'pending')->count();
        $message = "Approved {$approved} items.";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} duplicates.";
        }
        if ($remaining > 0) {
            $message .= " {$remaining} remaining.";
        }

        // Photos download runs in background via scheduler — skip here for speed

        return back()->with('success', $message);
    })->name('import.approve-all');

    // CSV Upload
    Route::post('/import/csv', function (Request $request) {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('csv_file');
        $csvData = array_map('str_getcsv', file($file->getRealPath()));
        $headers = array_shift($csvData);

        $batch = ImportBatch::create([
            'agent_id' => null,
            'source' => 'csv',
            'name' => 'CSV: '.$file->getClientOriginalName(),
            'total' => count($csvData),
            'status' => 'processing',
            'pending' => count($csvData),
        ]);

        $imported = 0;
        $skipped = 0;

        // Pre-load existing for duplicate detection (exclude soft-deleted)
        $existingNames = Business::withoutTrashed()->pluck('name')->map(fn ($n) => strtolower($n))->toArray();

        foreach ($csvData as $row) {
            $data = array_combine($headers, $row);

            $name = $data['name'] ?? $data['business_name'] ?? null;
            if (! $name) {
                $skipped++;

                continue;
            }

            // Duplicate check
            if (in_array(strtolower(trim($name)), $existingNames)) {
                $skipped++;

                continue;
            }

            ImportItem::create([
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
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} duplicates/invalid.";
        }

        return back()->with('success', $message);
    })->name('import.csv');

    // ─── Vendors (Owner Management) ─── admin + super_admin only
    Route::get('/vendors', function () {
        if (! in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            abort(403);
        }

        $query = User::where('role', 'owner')->withCount('ownedBusinesses');

        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
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
        if (! in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            abort(403);
        }

        $vendor = User::where('role', 'owner')->findOrFail($id);
        $businesses = Business::where('created_by', $vendor->id)->with('category')->get();
        $recentActivity = ActivityLog::where('user_id', $vendor->id)->latest()->take(10)->get();

        return view('admin.vendors.show', compact('vendor', 'businesses', 'recentActivity'));
    })->name('vendors.show');

    // ─── Staff Management ─── super_admin only
    Route::get('/staff', function () {
        if (! in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            abort(403);
        }

        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator'])->latest()->paginate(20)->withQueryString();

        return view('admin.staff.index', compact('staff'));
    })->name('staff');

    Route::get('/staff/create', function () {
        if (! in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            abort(403);
        }

        return view('admin.staff.form', ['staff' => null]);
    })->name('staff.create');

    Route::post('/staff', function (Request $request) {
        if (! in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            abort(403);
        }

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

        ActivityLogService::log('staff_created', $staff, ['role' => $staff->role]);

        return redirect()->route('admin.staff')->with('success', 'Staff member created.');
    })->name('staff.store');

    Route::get('/staff/{id}', function ($id) {
        if (! in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            abort(403);
        }

        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator'])->findOrFail($id);

        return view('admin.staff.show', compact('staff'));
    })->name('staff.show');

    Route::get('/staff/{id}/edit', function ($id) {
        if (! in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            abort(403);
        }

        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator'])->findOrFail($id);

        return view('admin.staff.form', compact('staff'));
    })->name('staff.edit');

    Route::put('/staff/{id}', function (Request $request, $id) {
        if (! in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            abort(403);
        }

        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator'])->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$staff->id,
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
        ActivityLogService::log('staff_updated', $staff);

        return redirect()->route('admin.staff')->with('success', 'Staff member updated.');
    })->name('staff.update');

    Route::delete('/staff/{id}', function ($id) {
        if (! in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            abort(403);
        }

        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator'])->findOrFail($id);

        if ($staff->id === Auth::id()) {
            return back()->with('error', 'Cannot delete your own account.');
        }

        ActivityLogService::log('staff_deleted', $staff, ['name' => $staff->name]);
        $staff->delete();

        return redirect()->route('admin.staff')->with('success', 'Staff member deleted.');
    })->name('staff.destroy');

    // ─── Activity Logs ─── admin + super_admin
    Route::get('/activity-logs', function () {
        if (! in_array(Auth::user()->role, ['super_admin', 'admin'])) {
            abort(403);
        }

        $query = ActivityLog::with('user')->latest();

        if ($action = request('action')) {
            $query->where('action', $action);
        }

        if ($userId = request('user_id')) {
            $query->where('user_id', $userId);
        }

        $logs = $query->paginate(50)->withQueryString();
        $actions = ActivityLog::distinct()->pluck('action');
        $users = User::whereIn('id', ActivityLog::distinct()->pluck('user_id'))->get(['id', 'name']);

        return view('admin.activity-logs', compact('logs', 'actions', 'users'));
    })->name('activity-logs');

    // ─── Areas CRUD ───
    Route::get('/areas', function () {
        $areas = Area::withCount('businesses')->orderBy('order')->paginate(20);

        return view('admin.areas.index', compact('areas'));
    })->name('areas');

    Route::get('/areas/create', function () {
        return view('admin.areas.form');
    })->name('areas.create');

    Route::post('/areas', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'district' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'bounds_north' => 'nullable|numeric',
            'bounds_south' => 'nullable|numeric',
            'bounds_east' => 'nullable|numeric',
            'bounds_west' => 'nullable|numeric',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);
        $validated['is_active'] = $request->has('is_active');
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        $validated['pincodes'] = $request->filled('pincodes') ? array_map('trim', explode("\n", trim($request->pincodes))) : null;
        Area::create($validated);

        return redirect()->route('admin.areas')->with('success', 'Area created.');
    })->name('areas.store');

    Route::get('/areas/{id}/edit', function ($id) {
        $area = Area::findOrFail($id);

        return view('admin.areas.form', compact('area'));
    })->name('areas.edit');

    Route::put('/areas/{id}', function (Request $request, $id) {
        $area = Area::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'district' => 'required|string|max:255',
            'state' => 'required|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'bounds_north' => 'nullable|numeric',
            'bounds_south' => 'nullable|numeric',
            'bounds_east' => 'nullable|numeric',
            'bounds_west' => 'nullable|numeric',
            'order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);
        $validated['is_active'] = $request->has('is_active');
        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }
        $validated['pincodes'] = $request->filled('pincodes') ? array_map('trim', explode("\n", trim($request->pincodes))) : null;
        $area->update($validated);

        return redirect()->route('admin.areas')->with('success', 'Area updated.');
    })->name('areas.update');

    Route::delete('/areas/{id}', function ($id) {
        Area::findOrFail($id)->delete();

        return redirect()->route('admin.areas')->with('success', 'Area deleted.');
    })->name('areas.destroy');

    // ─── Pincode Management ───
    Route::get('/pincodes', function () {
        $pinned = \App\Models\Setting::get('pinned_states', []);
        if (is_string($pinned)) $pinned = json_decode($pinned, true) ?? [];

        $states = \App\Models\Pincode::selectRaw('state, COUNT(*) as total, SUM(serviceable) as serviceable')
            ->groupBy('state')
            ->orderBy('state')
            ->get()
            ->map(fn ($s) => [
                'state' => $s->state,
                'total' => (int) $s->total,
                'serviceable' => (int) $s->serviceable,
                'serviceable_percent' => $s->total > 0 ? round(($s->serviceable / $s->total) * 100) : 0,
                'pinned' => in_array($s->state, $pinned),
            ])
            ->sortByDesc(fn ($s) => $s['pinned'])
            ->values();

        return view('admin.pincodes.index', compact('states'));
    })->name('pincodes');

    Route::get('/pincodes/{state}', function ($state) {
        $districts = App\Models\Pincode::selectRaw('district, COUNT(*) as total, SUM(serviceable) as serviceable')
            ->where('state', $state)
            ->groupBy('district')
            ->orderBy('district')
            ->get()
            ->map(fn ($d) => [
                'district' => $d->district,
                'total' => (int) $d->total,
                'serviceable' => (int) $d->serviceable,
                'serviceable_percent' => $d->total > 0 ? round(($d->serviceable / $d->total) * 100) : 0,
            ]);
        $serviceableCount = $districts->sum('serviceable');

        return view('admin.pincodes.districts', compact('state', 'districts', 'serviceableCount'));
    })->name('pincodes.districts');

    Route::get('/pincodes/{state}/{district}', function ($state, $district) {
        $pincodes = App\Models\Pincode::where('state', $state)
            ->where('district', $district)
            ->orderBy('pincode')
            ->paginate(50);
        $serviceableCount = $pincodes->total() > 0
            ? App\Models\Pincode::where('state', $state)->where('district', $district)->where('serviceable', true)->count()
            : 0;

        return view('admin.pincodes.localities', compact('state', 'district', 'pincodes', 'serviceableCount'));
    })->name('pincodes.localities');

    Route::post('/pincodes/toggle-state', function (\Illuminate\Http\Request $request) {
        $state = $request->input('state');
        $enable = $request->boolean('enable');
        App\Models\Pincode::where('state', $state)->update(['serviceable' => $enable]);

        return redirect()->route('admin.pincodes')->with('success', ($enable ? 'Enabled' : 'Disabled') . " all pincodes in {$state}.");
    })->name('pincodes.toggle-state');

    Route::post('/pincodes/toggle-district', function (\Illuminate\Http\Request $request) {
        $state = $request->input('state');
        $district = $request->input('district');
        $enable = $request->boolean('enable');
        App\Models\Pincode::where('state', $state)->where('district', $district)->update(['serviceable' => $enable]);

        return redirect()->back()->with('success', ($enable ? 'Enabled' : 'Disabled') . " all pincodes in {$district}, {$state}.");
    })->name('pincodes.toggle-district');

    Route::post('/pincodes/toggle-pincode', function (\Illuminate\Http\Request $request) {
        $id = $request->input('id');
        $state = $request->input('state');
        $district = $request->input('district');
        $pincode = App\Models\Pincode::findOrFail($id);
        $pincode->update(['serviceable' => ! $pincode->serviceable]);

        return redirect()->route('admin.pincodes.localities', [$state, $district])->with('success', "Pincode {$pincode->pincode} " . ($pincode->serviceable ? 'enabled' : 'disabled') . ".");
    })->name('pincodes.toggle-pincode');

    Route::post('/pincodes/toggle-pin', function (\Illuminate\Http\Request $request) {
        $state = $request->input('state');
        $pinned = \App\Models\Setting::get('pinned_states', []);
        if (is_string($pinned)) $pinned = json_decode($pinned, true) ?? [];

        if (in_array($state, $pinned)) {
            $pinned = array_values(array_filter($pinned, fn ($s) => $s !== $state));
            $message = "{$state} unpinned.";
        } else {
            $pinned[] = $state;
            $message = "{$state} pinned.";
        }

        \App\Models\Setting::set('pinned_states', json_encode(array_values($pinned)), 'general');

        return redirect()->route('admin.pincodes')->with('success', $message);
    })->name('pincodes.toggle-pin');

    // ─── Area Interest / Coming Soon Leads ───
    Route::get('/area-interests', function () {
        $interests = \App\Models\AreaInterest::latest()->paginate(50);

        return view('admin.area-interests', compact('interests'));
    })->name('area-interests');

    // ─── Bookings Management ───
    Route::get('/bookings', function () {
        $query = Booking::with(['business:id,name', 'service:id,name'])->latest('booking_date');

        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($safe) {
                $q->where('customer_name', 'like', $safe)
                    ->orWhere('customer_phone', 'like', $safe);
            });
        }
        if ($status = request('status')) {
            $query->where('status', $status);
        }
        if ($date = request('date')) {
            $query->whereDate('booking_date', $date);
        }
        if ($businessId = request('business_id')) {
            $query->where('business_id', $businessId);
        }

        $bookings = $query->paginate(20)->withQueryString();

        return view('admin.bookings.index', compact('bookings'));
    })->name('bookings');

    Route::delete('/bookings/{id}', function ($id) {
        Booking::findOrFail($id)->delete();

        return redirect()->route('admin.bookings')->with('success', 'Booking deleted.');
    })->name('bookings.destroy');

    // ─── Orders Management ───
    Route::get('/orders', function () {
        $query = Order::with(['business:id,name', 'items'])->latest();

        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($safe) {
                $q->where('customer_name', 'like', $safe)
                    ->orWhere('order_number', 'like', $safe);
            });
        }
        if ($status = request('status')) {
            $query->where('status', $status);
        }
        if ($paymentStatus = request('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        $orders = $query->paginate(20)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    })->name('orders');

    Route::delete('/orders/{id}', function ($id) {
        $order = Order::findOrFail($id);
        $order->items()->delete();
        $order->delete();

        return redirect()->route('admin.orders')->with('success', 'Order deleted.');
    })->name('orders.destroy');

    // ─── Reviews Moderation ───
    Route::get('/reviews', function () {
        $query = Review::with(['user:id,name', 'business:id,name'])->latest();

        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($safe) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', $safe))
                    ->orWhereHas('business', fn ($b) => $b->where('name', 'like', $safe));
            });
        }
        if ($rating = request('rating')) {
            $query->where('rating', $rating);
        }
        if ($businessId = request('business_id')) {
            $query->where('business_id', $businessId);
        }

        $reviews = $query->paginate(20)->withQueryString();

        return view('admin.reviews.index', compact('reviews'));
    })->name('reviews');

    Route::delete('/reviews/{id}', function ($id) {
        Review::findOrFail($id)->delete();

        return redirect()->route('admin.reviews')->with('success', 'Review deleted.');
    })->name('reviews.destroy');

    // ─── Services Management ───
    Route::get('/services', function () {
        $query = Service::with(['business:id,name', 'bookings'])->orderBy('name');

        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where('name', 'like', $safe);
        }
        if ($businessId = request('business_id')) {
            $query->where('business_id', $businessId);
        }
        if (request()->has('is_active') && request('is_active') !== '') {
            $query->where('is_active', request('is_active') === '1');
        }

        $services = $query->paginate(20)->withQueryString();

        return view('admin.services.index', compact('services'));
    })->name('services');

    Route::get('/services/{id}/edit', function ($id) {
        $service = Service::with('business')->findOrFail($id);

        return view('admin.services.form', compact('service'));
    })->name('services.edit');

    Route::put('/services/{id}', function (Request $request, $id) {
        $service = Service::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration' => 'required|integer|min:15',
            'capacity' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
        ]);
        $validated['is_active'] = $request->has('is_active');
        $service->update($validated);

        return redirect()->route('admin.services')->with('success', 'Service updated.');
    })->name('services.update');

    Route::delete('/services/{id}', function ($id) {
        Service::findOrFail($id)->delete();

        return redirect()->route('admin.services')->with('success', 'Service deleted.');
    })->name('services.destroy');

    // ─── Transactions ───
    Route::get('/transactions', function () {
        $query = Transaction::with('user:id,name')->latest();

        if ($type = request('type')) {
            $query->where('type', $type);
        }
        if ($status = request('status')) {
            $query->where('status', $status);
        }
        if ($method = request('payment_method')) {
            $query->where('payment_method', $method);
        }

        $transactions = $query->paginate(20)->withQueryString();

        return view('admin.transactions.index', compact('transactions'));
    })->name('transactions');
});

// ─── Vendor / Owner Web Dashboard ───
Route::prefix('vendor')->name('vendor.')->middleware('web')->group(function () {

    Route::get('/login', function () {
        return view('vendor.login');
    })->name('login');

    Route::post('/login', function (Request $request) {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            if (! in_array($user->role, ['owner', 'admin', 'super_admin'])) {
                Auth::logout();

                return back()->withErrors(['email' => 'You do not have vendor access.'])->withInput();
            }

            return redirect()->intended(route('vendor.dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid credentials.'])->withInput();
    })->name('login.post');

    Route::post('/logout', function () {
        Auth::logout();

        return redirect()->route('vendor.login');
    })->name('logout');

    // Protected vendor routes
    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', function () {
            $user = Auth::user();
            $businesses = Business::where('created_by', $user->id)
                ->withCount(['bookings', 'orders', 'products'])->get();
            $totalBookings = $businesses->sum('bookings_count');
            $totalOrders = $businesses->sum('orders_count');
            $totalProducts = $businesses->sum('products_count');
            $recentBookings = Booking::whereIn('business_id', $businesses->pluck('id'))
                ->with('business:id,name', 'service:id,name')->latest()->take(5)->get();
            $recentOrders = Order::whereIn('business_id', $businesses->pluck('id'))
                ->with('business:id,name')->latest()->take(5)->get();

            return view('vendor.dashboard.index', compact(
                'user', 'businesses', 'totalBookings', 'totalOrders', 'totalProducts',
                'recentBookings', 'recentOrders'
            ));
        })->name('dashboard');

        // My Businesses
        Route::get('/businesses', function () {
            $user = Auth::user();
            $businesses = Business::where('created_by', $user->id)
                ->with('category:id,name')->latest()->paginate(20);

            return view('vendor.businesses.index', compact('businesses'));
        })->name('businesses');

        Route::get('/businesses/{id}/edit', function ($id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($id);

            return view('vendor.businesses.form', compact('business'));
        })->name('businesses.edit');

        Route::put('/businesses/{id}', function (Request $request, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($id);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'address' => 'nullable|string',
                'phone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'website' => 'nullable|url|max:255',
                'working_hours' => 'nullable|string',
            ]);
            if ($request->filled('working_hours')) {
                $validated['working_hours'] = json_decode($request->working_hours, true) ?? $business->working_hours;
            } else {
                unset($validated['working_hours']);
            }
            $business->update($validated);

            return redirect()->route('vendor.businesses')->with('success', 'Business updated.');
        })->name('businesses.update');

        // Products
        Route::get('/businesses/{businessId}/products', function ($businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            $products = Product::where('business_id', $business->id)->latest()->paginate(20);

            return view('vendor.products.index', compact('products', 'business'));
        })->name('products');

        Route::get('/businesses/{businessId}/products/create', function ($businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);

            return view('vendor.products.form', compact('business'));
        })->name('products.create');

        Route::post('/businesses/{businessId}/products', function (Request $request, $businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'nullable|numeric|min:0',
                'stock' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);
            $validated['business_id'] = $business->id;
            $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);
            $validated['is_active'] = $request->has('is_active');
            Product::create($validated);

            return redirect()->route('vendor.products', $business->id)->with('success', 'Product created.');
        })->name('products.store');

        Route::get('/businesses/{businessId}/products/{id}/edit', function ($businessId, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            $product = Product::where('business_id', $business->id)->findOrFail($id);

            return view('vendor.products.form', compact('business', 'product'));
        })->name('products.edit');

        Route::put('/businesses/{businessId}/products/{id}', function (Request $request, $businessId, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            $product = Product::where('business_id', $business->id)->findOrFail($id);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'nullable|numeric|min:0',
                'stock' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);
            $validated['is_active'] = $request->has('is_active');
            $product->update($validated);

            return redirect()->route('vendor.products', $business->id)->with('success', 'Product updated.');
        })->name('products.update');

        Route::delete('/businesses/{businessId}/products/{id}', function ($businessId, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            Product::where('business_id', $business->id)->findOrFail($id)->delete();

            return redirect()->route('vendor.products', $business->id)->with('success', 'Product deleted.');
        })->name('products.destroy');

        // Services
        Route::get('/businesses/{businessId}/services', function ($businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            $services = Service::where('business_id', $business->id)->orderBy('sort_order')->paginate(20);

            return view('vendor.services.index', compact('services', 'business'));
        })->name('services');

        Route::get('/businesses/{businessId}/services/create', function ($businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);

            return view('vendor.services.form', compact('business'));
        })->name('services.create');

        Route::post('/businesses/{businessId}/services', function (Request $request, $businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'duration' => 'required|integer|min:15',
                'capacity' => 'nullable|integer|min:1',
                'is_active' => 'nullable|boolean',
            ]);
            $validated['business_id'] = $business->id;
            $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);
            $validated['is_active'] = $request->has('is_active');
            Service::create($validated);

            return redirect()->route('vendor.services', $business->id)->with('success', 'Service created.');
        })->name('services.store');

        Route::get('/businesses/{businessId}/services/{id}/edit', function ($businessId, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($id);

            return view('vendor.services.form', compact('business', 'service'));
        })->name('services.edit');

        Route::put('/businesses/{businessId}/services/{id}', function (Request $request, $businessId, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($id);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'duration' => 'required|integer|min:15',
                'capacity' => 'nullable|integer|min:1',
                'is_active' => 'nullable|boolean',
            ]);
            $validated['is_active'] = $request->has('is_active');
            $service->update($validated);

            return redirect()->route('vendor.services', $business->id)->with('success', 'Service updated.');
        })->name('services.update');

        Route::delete('/businesses/{businessId}/services/{id}', function ($businessId, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            Service::where('business_id', $business->id)->findOrFail($id)->delete();

            return redirect()->route('vendor.services', $business->id)->with('success', 'Service deleted.');
        })->name('services.destroy');

        // Bookings
        Route::get('/businesses/{businessId}/bookings', function ($businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            $query = Booking::where('business_id', $business->id)->with('service:id,name')->latest('booking_date');

            if ($status = request('status')) {
                $query->where('status', $status);
            }
            if ($date = request('date')) {
                $query->whereDate('booking_date', $date);
            }

            $bookings = $query->paginate(20)->withQueryString();

            return view('vendor.bookings.index', compact('bookings', 'business'));
        })->name('bookings');

        Route::put('/bookings/{id}/status', function (Request $request, $id) {
            $booking = Booking::with('business')->findOrFail($id);
            $user = Auth::user();
            if ($booking->business->created_by !== $user->id) {
                abort(403);
            }

            $validated = $request->validate([
                'status' => 'required|in:confirmed,cancelled,completed,no_show',
                'cancellation_reason' => 'nullable|string',
            ]);
            $booking->update($validated);

            $ts = match ($validated['status']) {
                'confirmed' => 'confirmed_at',
                'completed' => 'completed_at',
                'cancelled' => 'cancelled_at',
                default => null,
            };
            if ($ts) {
                $booking->update([$ts => now()]);
            }

            return back()->with('success', 'Booking '.$validated['status'].'.');
        })->name('bookings.status');

        // Orders
        Route::get('/businesses/{businessId}/orders', function ($businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            $query = Order::where('business_id', $business->id)->with('items')->latest();

            if ($status = request('status')) {
                $query->where('status', $status);
            }
            if ($paymentStatus = request('payment_status')) {
                $query->where('payment_status', $paymentStatus);
            }

            $orders = $query->paginate(20)->withQueryString();

            return view('vendor.orders.index', compact('orders', 'business'));
        })->name('orders');

        Route::put('/orders/{id}/status', function (Request $request, $id) {
            $order = Order::with('business')->findOrFail($id);
            $user = Auth::user();
            if ($order->business->created_by !== $user->id) {
                abort(403);
            }

            $validated = $request->validate([
                'status' => 'required|in:confirmed,preparing,ready,out_for_delivery,delivered,cancelled',
                'cancellation_reason' => 'nullable|string',
            ]);
            $order->update($validated);

            $ts = match ($validated['status']) {
                'confirmed' => 'confirmed_at',
                'ready' => 'ready_at',
                'delivered' => 'delivered_at',
                'cancelled' => 'cancelled_at',
                default => null,
            };
            if ($ts) {
                $order->update([$ts => now()]);
            }

            return back()->with('success', 'Order '.$validated['status'].'.');
        })->name('orders.status');
    });
});
