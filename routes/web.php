<?php

use App\Http\Controllers\Admin\CapabilityTemplateController;
use App\Http\Controllers\Admin\ClassificationAuditController;
use App\Http\Controllers\Admin\HomepageContentController;
use App\Http\Controllers\Admin\TaxonomySuggestionController;
use App\Http\Controllers\Api\AiAgentController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Vendor\AnalyticsController;
use App\Http\Controllers\Vendor\MediaController;
use App\Http\Controllers\VendorNotificationController;
use App\Models\ActivityLog;
use App\Models\AgentImportedBusiness;
use App\Models\AiAgent;
use App\Models\Area;
use App\Models\AreaInterest;
use App\Models\Booking;
use App\Models\Business;
use App\Models\CapabilityTemplate;
use App\Models\Category;
use App\Models\City;
use App\Models\ClaimRequest;
use App\Models\ClaimVerification;
use App\Models\FeatureFlag;
use App\Models\ImportBatch;
use App\Models\ImportItem;
use App\Models\IntegrationApiKey;
use App\Models\MediaLibrary;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Report;
use App\Models\Review;
use App\Models\ScheduleBooking;
use App\Models\SearchHistory;
use App\Models\Service;
use App\Models\Setting;
use App\Models\ShopSection;
use App\Models\SubscriptionPlan;
use App\Models\TimeSlot;
use App\Models\Transaction;
use App\Models\TransportRoute;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleRental;
use App\Models\VehicleSchedule;
use App\Models\VehicleType;
use App\Models\VendorSetup;
use App\Models\World;
use App\Services\ActivityLogService;
use App\Services\BookingTypeResolver;
use App\Services\BookingWorkflowService;
use App\Services\BusinessHours;
use App\Services\BusinessModuleService;
use App\Services\ClaimNotificationService;
use App\Services\Experience\BusinessExperienceService;
use App\Services\ImportMergeService;
use App\Services\LaunchControlService;
use App\Services\MonetizationService;
use App\Services\NotificationService;
use App\Services\OperationalHealthService;
use App\Services\OrderWorkflowService;
use App\Services\PlaceLinkImporter;
use App\Services\PlanGate;
use App\Services\TripWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Public Routes
Route::get('/', function () {
    return view('public.home');
})->name('home');

// Mobile app download page
Route::get('/download', function () {
    $apkPath = public_path('downloads/EihoOne.apk');
    $version = \App\Models\Setting::get('app_version', '1.0.0');
    $apkExists = file_exists($apkPath);
    $apkSize = $apkExists ? number_format(filesize($apkPath) / 1048576, 1).' MB' : null;

    return view('public.download', compact('version', 'apkExists', 'apkSize'));
})->name('download');

Route::get('/businesses', function () {
    $query = Business::where('is_active', true)->with('category', 'subcategory', 'area');

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
    $launchControl = app(LaunchControlService::class);
    $query = Business::where('is_active', true)->with('category', 'subcategory', 'area');

    if ($module = request('module')) {
        $required = match ($module) {
            'ordering' => ['world.shop', 'module.catalog'],
            'booking' => ['world.book', 'module.bookings'],
            'transport' => ['world.ride', 'module.transport'],
            'directory' => ['world.discover', 'experience.directory'],
            default => [],
        };
        foreach ($required as $feature) {
            abort_unless($launchControl->enabled($feature), 404);
        }
        $query->ofModule($module);
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

    $mapBusinesses = (clone $query)
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->select(['id', 'category_id', 'area_id', 'name', 'slug', 'address', 'latitude', 'longitude'])
        ->limit(500)
        ->get();

    $sort = request('sort', 'latest');
    $query = match ($sort) {
        'rating' => $query->orderByDesc('average_rating'),
        'name' => $query->orderBy('name'),
        default => $query->latest(),
    };

    $businesses = $query->paginate(12)->withQueryString();
    $categories = Category::active()->orderBy('name')->get(['id', 'name', 'slug']);
    $areas = Area::active()
        ->where('slug', '!=', 'other')
        ->withCount(['businesses' => fn ($businessQuery) => $businessQuery->active()])
        ->orderBy('name')
        ->get(['id', 'name', 'slug'])
        ->filter(fn ($area) => $area->businesses_count > 0)
        ->values();

    return view('public.explore', compact('areas', 'businesses', 'categories', 'mapBusinesses'));
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
    $query = $area->businesses()->active()->with('category');

    if ($category = request('category')) {
        $query->whereHas('category', fn ($q) => $q->where('slug', $category));
    }

    $businesses = $query->latest()->paginate(12)->withQueryString();

    return view('public.area', compact('area', 'businesses'));
})->name('public.area');

Route::get('/map', function () {
    $businesses = Business::where('is_active', true)
        ->whereNotNull('latitude')
        ->whereNotNull('longitude')
        ->with('category')
        ->get();

    return view('public.map', compact('businesses'));
})->name('public.map');

Route::get('/category/{slug}', function ($slug) {
    $category = Category::where('slug', $slug)->firstOrFail();
    $businesses = $category->businesses()->where('businesses.is_active', true)->latest()->paginate(12);

    return view('public.category', compact('category', 'businesses'));
})->name('public.category');

Route::get('/business/{slug}', function ($slug) {
    $business = Business::where('slug', $slug)
        ->where('is_active', true)
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
        $message = "Your Eiho One verification code is: *{$otp}\n\nThis code expires in 10 minutes.\nDo not share this code with anyone.";
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
                "Your Eiho One verification code is: {$otp}\n\nThis code expires in 10 minutes.",
                function ($mail) use ($request) {
                    $mail->to($request['email'])
                        ->subject('Eiho One — Verification Code')
                        ->from('noreply@hola.ehlom.com', 'Eiho One');
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
})->middleware('throttle:5,1')->name('public.claim.send-otp');

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
            'password' => Hash::make(Str::random(16)),
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
})->middleware('throttle:5,1')->name('public.claim.verify.submit');

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
            $message = "Your Eiho One verification code is: *{$otp}\n\nThis code expires in 10 minutes.";
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
                "Your Eiho One verification code is: {$otp}\n\nThis code expires in 10 minutes.",
                function ($mail) use ($verification) {
                    $mail->to($verification->email)
                        ->subject('Eiho One — Verification Code')
                        ->from('noreply@hola.ehlom.com', 'Eiho One');
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
})->middleware('throttle:5,1')->name('public.claim.resend-otp');

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
})->middleware('auth')->name('admin.logout');

// Admin Routes (protected)
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'admin.dept'])->group(function () {
    // One simple home for the business taxonomy. Categories are grouped by the
    // customer journey they create, while subcategories describe the business type.
    Route::get('/business-types', function () {
        $categories = Category::with([
            'subcategories' => fn ($query) => $query->orderBy('order')->orderBy('name'),
        ])->withCount('businesses')->orderBy('order')->orderBy('name')->get();

        $groups = [
            'directory' => [
                'title' => 'Directory',
                'description' => 'Listings people can explore, locate, call, or message.',
                'types' => ['directory'],
                'empty' => 'Add public services, professional services, community places, or local attractions here.',
            ],
            'booking' => [
                'title' => 'Booking',
                'description' => 'Businesses customers request, reserve, or book: taxi, turf, stays, and appointments.',
                'types' => ['booking', 'both'],
                'empty' => 'Add taxi, turf, hotel, appointment, or event types here.',
            ],
            'shopping' => [
                'title' => 'Shopping',
                'description' => 'Businesses with menus or products and offline/COD order requests.',
                'types' => ['ordering'],
                'empty' => 'Add restaurants, grocery stores, pharmacies, or retail types here.',
            ],
        ];

        foreach ($groups as $key => $group) {
            $groups[$key]['categories'] = $categories
                ->whereIn('module_type', $group['types'])
                ->values();
        }

        return view('admin.business-types.index', compact('groups'));
    })->name('business-types');

    // Business Types — browse businesses by type (like Flutter tabs but admin)
    $typeRoutes = [
        'shopping' => ['experiences' => ['retail', 'restaurant'], 'modules' => ['catalog'], 'label' => 'Shopping'],
        'booking' => ['experiences' => ['appointment', 'stay'], 'modules' => ['bookings'], 'label' => 'Booking'],
        'taxi' => ['experiences' => [], 'modules' => ['transport'], 'label' => 'Taxi / Transport'],
    ];

    foreach ($typeRoutes as $typeSlug => $typeConfig) {
        Route::get("/businesses-type/{$typeSlug}", function () use ($typeSlug, $typeConfig) {
            $query = Business::with('category')
                ->where(function ($q) use ($typeConfig) {
                    if (! empty($typeConfig['experiences'])) {
                        $q->whereJsonContains('enabled_experiences', $typeConfig['experiences'][0]);
                        foreach (array_slice($typeConfig['experiences'], 1) as $exp) {
                            $q->orWhereJsonContains('enabled_experiences', $exp);
                        }
                    }
                    if (! empty($typeConfig['modules'])) {
                        foreach ($typeConfig['modules'] as $module) {
                            $q->orWhere("enabled_modules->{$module}", true);
                        }
                    }
                });

            if ($search = request('search')) {
                $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(function ($q) use ($safe) {
                    $q->where('name', 'like', $safe)
                        ->orWhere('address', 'like', $safe)
                        ->orWhere('locality', 'like', $safe);
                });
            }

            if ($status = request('status')) {
                $query->where('is_active', $status === 'active');
            }

            $businesses = $query->latest()->paginate(20)->withQueryString();

            return view('admin.businesses.index', [
                'businesses' => $businesses,
                'typeFilter' => $typeSlug,
                'typeLabel' => $typeConfig['label'],
            ]);
        })->name("businesses-type.{$typeSlug}");
    }

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

        return view('admin.businesses.index', compact('businesses') + ['typeLabel' => null, 'typeFilter' => null]);
    })->name('businesses');

    // Detect business changes
    Route::post('/businesses/detect-changes', function () {
        Artisan::call('app:detect-business-changes', ['--limit' => request('limit', 50)]);
        $output = Artisan::output();

        return response()->json(['message' => 'Change detection completed', 'output' => $output]);
    })->name('businesses.detect-changes');

    Route::get('/businesses/create', function () {
        $categories = Category::orderBy('name')->get();
        $subcategories = Category::whereNotNull('parent_id')->orderBy('name')->get();

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
        $pincode = Pincode::lookup($validated['pincode']);
        if (! $pincode) {
            return back()->withErrors(['pincode' => 'Invalid pincode.'])->withInput();
        }
        $validated['slug'] = $request->slug ?: Str::slug($request->name);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['district'] = $pincode->district;
        $validated['state'] = $pincode->state;
        $validated['created_by'] = Auth::id();
        $validated['payment_methods'] = collect($request->input('payment_methods', []))->filter(fn ($v) => $v)->values()->toArray();

        $business = Business::create($validated);
        $business->syncPrimaryClassification($business->category_id, 'admin_created');
        ActivityLogService::log('business_created', null, ['name' => $validated['name']]);

        return redirect()->route('admin.businesses')->with('success', 'Business created.');
    })->name('businesses.store');

    Route::get('/businesses/{id}', function ($id) {
        $business = Business::with(['category', 'subcategory', 'products', 'reviews.user', 'user', 'createdBy', 'deliveryZones.area'])->findOrFail($id);
        $modules = app(BusinessModuleService::class)->effectiveFor($business);

        return view('admin.businesses.show', compact('business', 'modules'));
    })->name('businesses.show');

    Route::get('/businesses/{id}/edit', function ($id) {
        $business = Business::findOrFail($id);
        $categories = Category::orderBy('name')->get();
        $subcategories = Category::whereNotNull('parent_id')->orderBy('name')->get();

        return view('admin.businesses.form', compact('business', 'categories', 'subcategories'));
    })->name('businesses.edit');

    Route::get('/businesses/{id}/modules', function ($id) {
        $business = Business::with(['category', 'subcategory'])->findOrFail($id);
        $moduleService = app(BusinessModuleService::class);

        return view('admin.businesses.modules', [
            'business' => $business,
            'definitions' => BusinessModuleService::DEFINITIONS,
            'modules' => $moduleService->effectiveFor($business),
            'recommended' => $moduleService->recommendedFor($business),
            'readiness' => $moduleService->readiness($business),
            'globallyEnabledModules' => app(LaunchControlService::class)->enabledModuleKeys(),
        ]);
    })->name('businesses.modules');

    Route::put('/businesses/{id}/modules', function (Request $request, $id) {
        $business = Business::findOrFail($id);
        $validated = $request->validate([
            'modules' => 'nullable|array',
            'modules.*' => 'in:catalog,orders,bookings,inventory,transport,turf',
        ]);

        app(BusinessModuleService::class)->update($business, $validated['modules'] ?? []);

        return redirect()->route('admin.businesses.modules', $business->id)
            ->with('success', 'Business modules updated. Required dependencies were enabled automatically.');
    })->name('businesses.modules.update');

    Route::post('/businesses/{id}/verify', function ($id) {
        $business = Business::findOrFail($id);
        $business->update(['verification_status' => 'verified', 'is_active' => true]);
        ActivityLogService::log('business_verified', $business, ['business_id' => $business->id]);

        return back()->with('success', 'Business verified and now live.');
    })->name('businesses.verify');

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
            $pincode = Pincode::lookup($validated['pincode']);
            if (! $pincode) {
                return back()->withErrors(['pincode' => 'Invalid pincode.'])->withInput();
            }
            $validated['state'] = $pincode->state;
            $validated['district'] = $pincode->district;
        }

        $validated['slug'] = $request->slug ?: Str::slug($request->name);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['payment_methods'] = collect($request->input('payment_methods', []))->filter(fn ($v) => $v)->values()->toArray();

        $business->update($validated);
        $business->syncPrimaryClassification($business->category_id, 'admin_updated');
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
            'module_type' => 'required|in:directory,ordering,booking',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $validated['slug'] = $request->slug ?: Str::slug($request->name);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_canonical'] = $request->boolean('is_canonical', true);
        $validated['order'] = $request->order ?? 0;

        // Single-taxonomy: a category always resolves to exactly one world + tree position.
        Category::applyTaxonomy($validated, $validated['parent_id'] ?? null);

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
            'module_type' => 'required|in:directory,ordering,booking',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        // A category cannot be its own parent.
        if (($validated['parent_id'] ?? null) == $category->id) {
            $validated['parent_id'] = null;
        }

        $validated['slug'] = $request->slug ?: Str::slug($request->name);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_canonical'] = $request->boolean('is_canonical');
        $validated['order'] = $request->order ?? 0;

        Category::applyTaxonomy($validated, $validated['parent_id'] ?? null);

        $category->update($validated);

        return redirect()->route('admin.categories')->with('success', 'Category updated.');
    })->name('categories.update');

    Route::delete('/categories/{id}', function ($id) {
        Category::findOrFail($id)->delete();

        return redirect()->route('admin.categories')->with('success', 'Category deleted.');
    })->name('categories.destroy');

    // Subcategories — legacy table is read-only after being merged into
    // level-2 category children (migrate_subcategories_to_category_children).
    // The admin screen is hidden; everything redirects to the Categories screen.
    Route::get('/subcategories', function () {
        return redirect()->route('admin.categories')
            ->with('info', 'Subcategories have been merged into Categories as child categories.');
    })->name('subcategories');

    Route::get('/subcategories/create', function () {
        return redirect()->route('admin.categories.create')
            ->with('info', 'Subcategories have been merged into Categories — create a category with a parent instead.');
    })->name('subcategories.create');

    Route::post('/subcategories', function () {
        return redirect()->route('admin.categories')
            ->with('error', 'Subcategories are read-only legacy rows — create child categories under Categories instead.');
    })->name('subcategories.store');

    Route::get('/subcategories/{id}/edit', function () {
        return redirect()->route('admin.categories')
            ->with('info', 'Subcategories have been merged into Categories as child categories.');
    })->name('subcategories.edit');

    Route::put('/subcategories/{id}', function () {
        return redirect()->route('admin.categories')
            ->with('error', 'Subcategories are read-only legacy rows — edit child categories under Categories instead.');
    })->name('subcategories.update');

    Route::delete('/subcategories/{id}', function () {
        return redirect()->route('admin.categories')
            ->with('error', 'Subcategories are read-only legacy rows — delete child categories under Categories instead.');
    })->name('subcategories.destroy');

    // Products
    Route::get('/products', function () {
        $shopWorld = World::where('slug', 'shop')->first();
        $businessTypes = $shopWorld
            ? Category::where('world_id', $shopWorld->id)
                ->whereNull('parent_id')
                ->orderBy('order')->orderBy('name')
                ->get()
            : collect();
        $totalProducts = Product::count();

        $typeCounts = [];
        $typeCategoryIds = [];
        $allShopCategories = $shopWorld
            ? Category::where('world_id', $shopWorld->id)->get()->keyBy('id')
            : collect();

        $collectTypeIds = function (int $typeId, array &$ids) use (&$collectTypeIds, $allShopCategories): void {
            $ids[] = $typeId;
            foreach ($allShopCategories->where('parent_id', $typeId) as $child) {
                $collectTypeIds($child->id, $ids);
            }
        };

        foreach ($businessTypes as $type) {
            $ids = [];
            $collectTypeIds($type->id, $ids);
            $typeCategoryIds[$type->id] = $ids;
            $typeCounts[$type->id] = Product::whereHas('business', function ($q) use ($ids) {
                $q->where(fn ($b) => $b->whereIn('category_id', $ids)
                    ->orWhereHas('classifications', fn ($c) => $c->whereIn('category_id', $ids)->where('is_active', true)));
            })->count();
        }

        $query = Product::with(['business', 'category'])->orderBy('name');

        if ($typeId = request('business_type_id')) {
            $ids = $typeCategoryIds[(int) $typeId] ?? [$typeId];
            $query->whereHas('business', function ($q) use ($ids) {
                $q->where(fn ($b) => $b->whereIn('category_id', $ids)
                    ->orWhereHas('classifications', fn ($c) => $c->whereIn('category_id', $ids)->where('is_active', true)));
            });
        }
        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where('name', 'like', $safe);
        }

        $products = $query->paginate(20)->withQueryString();

        return view('admin.products.index', compact('products', 'businessTypes', 'typeCounts', 'totalProducts'));
    })->name('products')->middleware('launch:world.shop');

    Route::get('/products/create', function () {
        $businesses = Business::orderBy('name')->get();

        return view('admin.products.form', compact('businesses'));
    })->name('products.create')->middleware('launch:world.shop');

    Route::post('/products', function (Request $request) {
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|max:255',
            'menu_section' => 'nullable|string|max:100',
            'food_type' => 'nullable|in:veg,non_veg,egg,vegan,other',
            'preparation_minutes' => 'nullable|integer|min:1|max:1440',
            'available_from' => 'nullable|required_with:available_until|date_format:H:i',
            'available_until' => 'nullable|required_with:available_from|date_format:H:i',
            'sold_out_until' => 'nullable|date|after:now',
            'price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'availability' => 'nullable|in:in_stock,out_of_stock,limited',
        ]);
        $validated['slug'] = $request->slug ?: Str::slug($request->name);
        $validated['description'] = $request->description;
        $validated['is_active'] = $request->boolean('is_active');

        Product::create($validated);

        return redirect()->route('admin.products')->with('success', 'Product created.');
    })->name('products.store')->middleware('launch:world.shop');

    Route::get('/products/{id}/edit', function ($id) {
        $product = Product::findOrFail($id);
        $businesses = Business::orderBy('name')->get();

        return view('admin.products.form', compact('product', 'businesses'));
    })->name('products.edit')->middleware('launch:world.shop');

    Route::put('/products/{id}', function (Request $request, $id) {
        $product = Product::findOrFail($id);
        $validated = $request->validate([
            'business_id' => 'required|exists:businesses,id',
            'name' => 'required|max:255',
            'menu_section' => 'nullable|string|max:100',
            'food_type' => 'nullable|in:veg,non_veg,egg,vegan,other',
            'preparation_minutes' => 'nullable|integer|min:1|max:1440',
            'available_from' => 'nullable|required_with:available_until|date_format:H:i',
            'available_until' => 'nullable|required_with:available_from|date_format:H:i',
            'sold_out_until' => 'nullable|date|after:now',
            'price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'availability' => 'nullable|in:in_stock,out_of_stock,limited',
        ]);
        $validated['slug'] = $request->slug ?: Str::slug($request->name);
        $validated['description'] = $request->description;
        $validated['is_active'] = $request->boolean('is_active');

        $product->update($validated);

        return redirect()->route('admin.products')->with('success', 'Product updated.');
    })->name('products.update')->middleware('launch:world.shop');

    Route::delete('/products/{id}', function ($id) {
        Product::findOrFail($id)->delete();

        return redirect()->route('admin.products')->with('success', 'Product deleted.');
    })->name('products.destroy')->middleware('launch:world.shop');

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
        // Phase 6 lifecycle: ownership is transferred on claim approval, but the
        // business stays a directory listing (verification_status remains pending)
        // until an admin explicitly verifies it and it becomes a working vendor.
        $claim->business->update([
            'claim_status' => 'claimed',
            'created_by' => $claim->user_id,
            'is_active' => true,
        ]);
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
            $claim->business->update([
                'claim_status' => 'claimed',
                'created_by' => $claim->user_id,
                'is_active' => true,
            ]);
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
    })->name('settings')->middleware('admin:platform');

    Route::put('/settings', function (Request $request) {
        $settings = $request->input('settings', []);
        foreach ($settings as $key => $value) {
            Setting::set($key, $value, str_starts_with($key, 'smtp') ? 'smtp' : (str_starts_with($key, 'api_key') ? 'api' : 'general'));
        }

        return redirect()->route('admin.settings')->with('success', 'Settings saved.');
    })->name('settings.update');

    Route::post('/settings/upload', function (Request $request) {
        $request->validate([
            'type' => 'required|in:logo,favicon',
            'file' => 'required|image|mimes:jpeg,png,jpg,webp,svg,ico|max:2048',
        ]);

        $type = $request->type;
        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        $filename = 'branding/'.$type.'-'.Str::random(8).'.'.$ext;
        Storage::disk('public')->put($filename, file_get_contents($file));
        $url = 'storage/'.$filename;

        Setting::set($type === 'logo' ? 'logo_url' : 'favicon_url', $url, 'branding');

        return back()->with('success', ucfirst($type).' uploaded.');
    })->name('settings.upload');

    Route::post('/settings/test-email', function (Request $request) {
        $request->validate(['email' => 'required|email']);

        try {
            Mail::raw(
                "Eiho One SMTP Test\n\nThis is a test email from your Eiho One app.\n\nIf you received this, your SMTP configuration is working correctly!\n\nSent at: ".now()->format('Y-m-d H:i:s'),
                function ($message) use ($request) {
                    $message->to($request->email)
                        ->subject('Eiho One - SMTP Test Email')
                        ->from(config('mail.from.address', 'noreply@hola.app'), config('mail.from.name', 'Eiho One'));
                }
            );

            return response()->json(['message' => 'Test email sent successfully! Check your inbox.']);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to send: '.$e->getMessage()], 500);
        }
    })->name('settings.test-email');

    // ─── Message Center: notify businesses (claim invitations) ───
    Route::get('/message-center', function () {
        $service = app(ClaimNotificationService::class);
        $channels = $service->configuredChannels();
        $template = Setting::get('template_claim_sms', 'Hi {business_name}! Your business is now on {site_name} — {district}s #1 business directory. Claim it for free to update info, add photos, and reply to reviews. Claim now: {claim_url}');

        $ready = Business::where('claim_status', 'unclaimed')
            ->where('source', 'import')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNotNull('phone')->where('phone', '!=', ''));
        $readyCount = (clone $ready)->count();
        $noContactCount = Business::where('claim_status', 'unclaimed')
            ->where('source', 'import')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('phone')->orWhere('phone', ''))
            ->count();

        $recentLogs = NotificationLog::with('business:id,name')
            ->where('type', 'claim_invitation')
            ->latest()->take(20)->get();

        $autoPilot = [
            'enabled' => Setting::get('autopilot_claim_enabled', '0') === '1',
            'batch' => Setting::get('autopilot_claim_batch', 20),
            'channel' => Setting::get('autopilot_claim_channel', 'whatsapp_meta'),
            'first_after_days' => Setting::get('autopilot_first_after_days', 1),
            'reminder_after_days' => Setting::get('autopilot_reminder_after_days', 7),
            'max_reminders' => Setting::get('autopilot_max_reminders', 2),
        ];
        $reminderTemplate = Setting::get('template_claim_reminder', 'Hi {business_name}! Just a reminder — your business is listed on {site_name} and you can claim it for free. Claim now: {claim_url}');

        return view('admin.message-center.index', compact('channels', 'template', 'reminderTemplate', 'readyCount', 'noContactCount', 'recentLogs', 'autoPilot'));
    })->name('message-center');

    Route::post('/message-center/template', function (Request $request) {
        $request->validate(['template' => 'required|string|max:2000']);
        Setting::set('template_claim_sms', $request->template);

        return back()->with('success', 'Claim invitation template saved.');
    })->name('message-center.template');

    Route::post('/message-center/autopilot', function (Request $request) {
        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'batch' => 'nullable|integer|min:1|max:500',
            'channel' => 'nullable|in:whatsapp_meta,whatsapp,sms,telegram',
            'first_after_days' => 'nullable|integer|min:0|max:30',
            'reminder_after_days' => 'nullable|integer|min:1|max:60',
            'max_reminders' => 'nullable|integer|min:1|max:10',
            'reminder_template' => 'nullable|string|max:2000',
        ]);

        Setting::set('autopilot_claim_enabled', $request->boolean('enabled') ? '1' : '0');
        if (isset($validated['batch'])) {
            Setting::set('autopilot_claim_batch', $validated['batch']);
        }
        if (isset($validated['channel'])) {
            Setting::set('autopilot_claim_channel', $validated['channel']);
        }
        if (isset($validated['first_after_days'])) {
            Setting::set('autopilot_first_after_days', $validated['first_after_days']);
        }
        if (isset($validated['reminder_after_days'])) {
            Setting::set('autopilot_reminder_after_days', $validated['reminder_after_days']);
        }
        if (isset($validated['max_reminders'])) {
            Setting::set('autopilot_max_reminders', $validated['max_reminders']);
        }
        if (isset($validated['reminder_template'])) {
            Setting::set('template_claim_reminder', $validated['reminder_template']);
        }

        return back()->with('success', $request->boolean('enabled')
            ? 'Auto-pilot is ON — it will run daily at 10am.'
            : 'Auto-pilot is OFF — nothing will be sent.');
    })->name('message-center.autopilot');

    Route::post('/message-center/send', function (Request $request) {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:500',
            'channel' => 'required|in:whatsapp_meta,whatsapp,sms,telegram',
            'business_id' => 'nullable|integer|exists:businesses,id',
        ]);

        $service = app(ClaimNotificationService::class);
        $template = Setting::get('template_claim_sms', 'Hi {business_name}! Your business is now on {site_name} — claim it for free. {claim_url}');

        $query = Business::where('claim_status', 'unclaimed')
            ->where('source', 'import')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNotNull('phone')->where('phone', '!=', ''));

        if (! empty($validated['business_id'])) {
            $query->whereKey($validated['business_id']);
        }

        $businesses = $query->limit($validated['limit'] ?? 50)->get();

        $sent = 0;
        $failed = 0;
        $targets = 0;
        foreach ($businesses as $business) {
            $targets++;
            $message = $service->renderTemplate($template, $business);
            $result = $service->send($business, $message, $validated['channel']);
            if ($result['sent']) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return back()->with('success', "Sent to {$sent} businesses (attempted {$targets}, failed {$failed}). Check the log below.");
    })->name('message-center.send');

    Route::get('/message-center/logs', function (Request $request) {
        $logs = NotificationLog::with('business:id,name')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(50)->withQueryString();

        return view('admin.message-center.logs', compact('logs'));
    })->name('message-center.logs');

    // ─── Monetization: subscription plans ───
    Route::get('/subscription-plans', function () {
        $plans = SubscriptionPlan::orderBy('sort_order')->orderBy('price')->get();

        return view('admin.monetization.plans', compact('plans'));
    })->name('subscription-plans')->middleware('admin:platform');

    Route::post('/subscription-plans', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:subscription_plans,slug',
            'billing_interval' => 'required|in:monthly,yearly',
            'price' => 'required|numeric|min:0',
            'commission_percent' => 'required|numeric|min:0|max:100',
            'features' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        $validated['features'] = $validated['features']
            ? array_values(array_filter(array_map('trim', explode(',', $validated['features']))))
            : [];
        $validated['is_active'] = $request->has('is_active');
        SubscriptionPlan::create($validated);

        return back()->with('success', 'Plan created.');
    })->name('subscription-plans.store');

    Route::put('/subscription-plans/{id}', function (Request $request, $id) {
        $plan = SubscriptionPlan::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'billing_interval' => 'required|in:monthly,yearly',
            'price' => 'required|numeric|min:0',
            'commission_percent' => 'required|numeric|min:0|max:100',
            'features' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        $validated['features'] = $validated['features']
            ? array_values(array_filter(array_map('trim', explode(',', $validated['features']))))
            : [];
        $validated['is_active'] = $request->has('is_active');
        $plan->update($validated);

        return back()->with('success', 'Plan updated.');
    })->name('subscription-plans.update');

    Route::delete('/subscription-plans/{id}', function ($id) {
        SubscriptionPlan::findOrFail($id)->delete();

        return back()->with('success', 'Plan deleted.');
    })->name('subscription-plans.destroy');

    // ─── Monetization: platform earnings ───
    Route::get('/earnings', function (Request $request) {
        $service = app(MonetizationService::class);
        $earnings = $service->earnings($request->get('from'), $request->get('to'));

        return view('admin.monetization.earnings', compact('earnings'));
    })->name('earnings')->middleware('admin:platform');

    // ─── Monetization: per-business commission + subscription assignment ───
    Route::get('/businesses/{id}/monetization', function ($id) {
        $business = Business::with(['subscription.plan'])->findOrFail($id);
        $plans = SubscriptionPlan::active()->orderBy('price')->get();
        $service = app(MonetizationService::class);

        return view('admin.monetization.business', compact('business', 'plans', 'service'));
    })->name('businesses.monetization');

    Route::put('/businesses/{id}/monetization', function (Request $request, $id) {
        $business = Business::findOrFail($id);
        $validated = $request->validate([
            'commission_percent' => 'nullable|numeric|min:0|max:100',
            'plan_id' => 'nullable|exists:subscription_plans,id',
            'subscription_status' => 'nullable|in:active,trialing,cancelled',
        ]);

        $business->update(['commission_percent' => $validated['commission_percent'] ?? 0]);

        if (! empty($validated['plan_id'])) {
            $plan = SubscriptionPlan::findOrFail($validated['plan_id']);
            app(MonetizationService::class)->subscribe($business, $plan, $validated['subscription_status'] ?? 'active');
        } elseif (($validated['subscription_status'] ?? '') === 'cancelled' && $business->subscription) {
            $business->subscription->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        }

        return back()->with('success', 'Business monetization updated.');
    })->name('businesses.monetization.update');

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
            'pending_imports' => ImportItem::inPipeline()->count(),
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

    // ─── Booking / Order / Transport Analytics ───
    Route::get('/booking-analytics', function (Request $request) {
        $range = in_array($request->get('range'), ['7', '30', '90'], true) ? (int) $request->get('range') : 30;
        $since = now()->subDays($range - 1)->startOfDay();
        $currency = '₹';

        $revenueMap = [
            'bookings' => fn () => Booking::where('created_at', '>=', $since)
                ->selectRaw('DATE(created_at) as date, SUM(total_price) as amount, COUNT(*) as count')
                ->groupBy('date')->pluck('amount', 'date'),
            'orders' => fn () => Order::where('status', 'delivered')->where('created_at', '>=', $since)
                ->selectRaw('DATE(created_at) as date, SUM(total) as amount, COUNT(*) as count')
                ->groupBy('date')->pluck('amount', 'date'),
            'trips' => fn () => Trip::whereIn('status', ['completed', 'delivered'])->where('created_at', '>=', $since)
                ->selectRaw('DATE(created_at) as date, SUM(fare) as amount, COUNT(*) as count')
                ->groupBy('date')->pluck('amount', 'date'),
        ];

        $totals = [
            'bookings' => ['revenue' => Booking::where('created_at', '>=', $since)->sum('total_price'), 'count' => Booking::where('created_at', '>=', $since)->count()],
            'orders' => ['revenue' => Order::where('status', 'delivered')->where('created_at', '>=', $since)->sum('total'), 'count' => Order::where('status', 'delivered')->where('created_at', '>=', $since)->count()],
            'trips' => ['revenue' => Trip::whereIn('status', ['completed', 'delivered'])->where('created_at', '>=', $since)->sum('fare'), 'count' => Trip::whereIn('status', ['completed', 'delivered'])->where('created_at', '>=', $since)->count()],
        ];
        $grandRevenue = $totals['bookings']['revenue'] + $totals['orders']['revenue'] + $totals['trips']['revenue'];

        $dates = collect();
        for ($i = $range - 1; $i >= 0; $i--) {
            $dates->put(now()->subDays($i)->toDateString(), 0);
        }
        $trend = $dates->mapWithKeys(fn ($_, $date) => [
            $date => (float) ($revenueMap['bookings']()->get($date, 0) ?? 0)
                + (float) ($revenueMap['orders']()->get($date, 0) ?? 0)
                + (float) ($revenueMap['trips']()->get($date, 0) ?? 0),
        ]);

        $perBusiness = collect();
        foreach (Business::withCount(['bookings' => fn ($q) => $q->where('created_at', '>=', $since)])
            ->orderBy('name')->get() as $biz) {
            $bookingRev = (float) Booking::where('business_id', $biz->id)->where('created_at', '>=', $since)->sum('total_price');
            $orderRev = (float) Order::where('business_id', $biz->id)->where('status', 'delivered')->where('created_at', '>=', $since)->sum('total');
            $tripRev = (float) Trip::where('business_id', $biz->id)->whereIn('status', ['completed', 'delivered'])->where('created_at', '>=', $since)->sum('fare');
            $revenue = $bookingRev + $orderRev + $tripRev;
            if ($biz->bookings_count > 0 || $revenue > 0) {
                $perBusiness->push([
                    'id' => $biz->id,
                    'name' => $biz->name,
                    'bookings' => $biz->bookings_count,
                    'revenue' => $revenue,
                ]);
            }
        }
        $perBusiness = $perBusiness->sortByDesc('revenue')->values();

        if ($request->get('format') === 'csv') {
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['Business', 'Bookings', 'Revenue ('.now()->format('Y-m-d').')']);
            foreach ($perBusiness as $row) {
                fputcsv($handle, [$row['name'], $row['bookings'], number_format($row['revenue'], 2)]);
            }
            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            return response($csv, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="admin-booking-analytics-'.now()->format('Y-m-d').'.csv"',
            ]);
        }

        return view('admin.analytics.bookings', compact('range', 'since', 'currency', 'totals', 'grandRevenue', 'trend', 'perBusiness'));
    })->name('booking-analytics');

    // Featured Businesses
    Route::get('/featured', function () {
        $featured = Business::where('is_featured', true)->with('category')->orderByDesc('views_count')->get();

        return view('admin.featured.index', compact('featured'));
    })->name('featured');

    Route::patch('/featured/{id}/remove', function ($id) {
        $business = Business::findOrFail($id);
        $business->update(['is_featured' => false]);
        ActivityLogService::log('business_unfeatured', $business);

        return back()->with('success', 'Business removed from featured listings.');
    })->name('featured.remove');

    // ─── AI Agents ───
    $agentCtrl = AiAgentController::class;

    Route::get('/autopilot', function () {
        $agent = AiAgent::first();
        $recentTasks = $agent ? $agent->tasks()->latest()->take(20)->get() : collect();
        $pendingImports = ImportItem::inPipeline()->count();
        $totalBusinesses = Business::where('is_active', true)->count();
        $totalCategories = Category::count();
        $todaysTasks = $agent ? $agent->tasks()->where('created_at', '>=', now()->startOfDay())->count() : 0;
        $todaysImports = $agent ? $agent->tasks()->where('created_at', '>=', now()->startOfDay())->sum('imported_count') : 0;
        $lastRun = $agent ? $agent->tasks()->latest()->first() : null;
        $nextRun = now()->addMinutes(240 - (now()->timestamp % 240));
        $operations = app(OperationalHealthService::class)->snapshot();

        return view('admin.autopilot', compact(
            'agent', 'recentTasks', 'pendingImports', 'totalBusinesses',
            'totalCategories', 'todaysTasks', 'todaysImports', 'lastRun', 'nextRun',
            'operations'
        ));
    })->name('autopilot')->middleware('admin:platform');

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
            'search_bounds_north' => 'nullable|numeric|between:-90,90',
            'search_bounds_south' => 'nullable|numeric|between:-90,90',
            'search_bounds_east' => 'nullable|numeric|between:-180,180',
            'search_bounds_west' => 'nullable|numeric|between:-180,180',
        ]);

        Setting::set('search_district', $request->search_district, 'search');
        Setting::set('search_state', $request->search_state, 'search');
        Setting::set('search_zipcodes', $request->search_zipcodes, 'search');
        Setting::set('search_areas', $request->search_areas, 'search');

        foreach (['north', 'south', 'east', 'west'] as $dir) {
            Setting::set("search_bounds_{$dir}", $request->input("search_bounds_{$dir}", ''), 'search');
        }

        return back()->with('success', 'Search locations updated. Agent will use these on next run.');
    })->name('autopilot.location');

    Route::get('/agents', function () use ($agentCtrl) {
        $response = (new $agentCtrl)->index();
        $agents = json_decode($response->getContent(), true)['agents'];

        return view('admin.agents.index', compact('agents'));
    })->name('agents');

    Route::get('/taxonomy/suggestions', [TaxonomySuggestionController::class, 'index'])
        ->name('taxonomy.suggestions');
    Route::patch('/taxonomy/suggestions/{suggestion}', [TaxonomySuggestionController::class, 'resolve'])
        ->name('taxonomy.suggestions.resolve');

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
            $query = ImportItem::whereIn('status', array_merge(ImportItem::IN_PIPELINE, ['duplicate']))->with('batch:id,name,source');
        } else {
            $query = ImportItem::inPipeline()->with('batch:id,name,source');
        }
        if (request('batch_id')) {
            $query->where('batch_id', request('batch_id'));
        }
        $items = $query->latest()->paginate(20);

        return view('admin.import.review', compact('items', 'status'));
    })->name('import.review');

    // Import a business by pasting its Google Maps Share link.
    Route::get('/import/by-link', function () {
        $cities = City::active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'state']);
        $recent = ImportItem::where('data->source', 'link_import')
            ->orWhere('notes', 'like', '%Google Maps link%')
            ->latest()->take(10)->get();

        return view('admin.import.by-link', compact('cities', 'recent'));
    })->name('import.by-link');

    Route::post('/import/by-link', function (Request $request) {
        $validated = $request->validate([
            'link' => 'required|string|max:2000',
            'city_id' => 'nullable|exists:cities,id',
        ]);

        try {
            $item = app(PlaceLinkImporter::class)->importByLink(
                $validated['link'],
                $validated['city_id'] ?? null,
            );
        } catch (Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            return back()->withErrors(['link' => 'Import failed: '.$e->getMessage()])->withInput();
        }

        return redirect()->route('admin.import.review')
            ->with('success', 'Business imported to the review queue: '.($item->data['name'] ?? 'Business'));
    })->name('import.by-link.submit');

    Route::post('/import/review/{id}/approve', function ($id) {
        $item = ImportItem::with('batch')->findOrFail($id);
        $data = $item->data;

        // DUPLICATE CHECK — flag for merge (linked via duplicate_of) instead of rejecting.
        $existingBusiness = app(ImportMergeService::class)->findExistingDuplicate($item);

        if ($existingBusiness) {
            app(ImportMergeService::class)->flagDuplicate($item, $existingBusiness);

            return back()->with('error', "Duplicate: {$existingBusiness->name}. Flagged in Duplicates tab — merge it there to carry over its data.");
        }

        $taxonomy = resolveApprovedImportTaxonomy($data);
        if (! $taxonomy) {
            $item->update(['notes' => 'Needs Business Types review: no approved category mapping.']);

            return back()->with('error', 'This import has no approved Business Type yet. Map it in Business Types / Taxonomy Review before approving.');
        }
        $categoryId = $taxonomy['category_id'];
        $subcategoryId = $taxonomy['subcategory_id'];

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
        if ($newBusiness && $newBusiness->category_id) {
            $newBusiness->syncPrimaryClassification($newBusiness->category_id, 'import_approved');
        }
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

    Route::post('/import/review/{id}/merge', function ($id) {
        $item = ImportItem::with('duplicateOf')->findOrFail($id);
        $existing = $item->duplicateOf;

        if (! $existing) {
            return back()->with('error', 'This item is not linked to an existing business. Try approving it first to flag the duplicate.');
        }

        $fieldsCopied = app(ImportMergeService::class)->merge($item);

        return back()->with('success', "Merged into {$existing->name} (copied {$fieldsCopied} field".($fieldsCopied === 1 ? '' : 's').').');
    })->name('import.merge');

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

                // DUPLICATE CHECK — flag for merge instead of rejecting
                $existingBusiness = app(ImportMergeService::class)->findExistingDuplicate($item);

                if ($existingBusiness) {
                    app(ImportMergeService::class)->flagDuplicate($item, $existingBusiness);
                    $skipped++;

                    continue;
                }

                $taxonomy = resolveApprovedImportTaxonomy($data);
                if (! $taxonomy) {
                    $item->update(['notes' => 'Needs Business Types review: no approved category mapping.']);
                    $skipped++;

                    continue;
                }
                $categoryId = $taxonomy['category_id'];
                $subcategoryId = $taxonomy['subcategory_id'];

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
                if ($newBusiness && $newBusiness->category_id) {
                    $newBusiness->syncPrimaryClassification($newBusiness->category_id, 'import_approved');
                }
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
        $items = ImportItem::inPipeline()->with('batch')->get();
        $approved = 0;
        $skipped = 0;

        // Pre-load categories and existing businesses for fast matching
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
                    $existingBusiness = app(ImportMergeService::class)->findExistingDuplicate($item);
                    if ($existingBusiness) {
                        app(ImportMergeService::class)->flagDuplicate($item, $existingBusiness);
                    } else {
                        $item->update(['status' => 'rejected', 'notes' => 'Duplicate detected during approve-all']);
                        if ($item->batch) {
                            $item->batch->increment('rejected');
                            $item->batch->decrement('pending');
                        }
                    }
                    $skipped++;

                    continue;
                }

                $taxonomy = resolveApprovedImportTaxonomy($data);
                if (! $taxonomy) {
                    $item->update(['notes' => 'Needs Business Types review: no approved category mapping.']);
                    $skipped++;

                    continue;
                }
                $categoryId = $taxonomy['category_id'];
                $subcategoryId = $taxonomy['subcategory_id'];

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
                if ($newBusiness && $newBusiness->category_id) {
                    $newBusiness->syncPrimaryClassification($newBusiness->category_id, 'import_approved');
                }
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

        $remaining = ImportItem::inPipeline()->count();
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
    // Business Owners: joint view of each owned business + its owner,
    // with business type (Shopping/Booking/Taxi), sub-type (Directory category),
    // verification status, and owner suspend. One row per owned business.
    Route::get('/vendors', function () {
        $query = Business::query()
            ->with(['createdBy', 'category'])
            ->whereNotNull('created_by');

        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($safe) {
                $q->where('name', 'like', $safe)
                    ->orWhereHas('createdBy', function ($oq) use ($safe) {
                        $oq->where('name', 'like', $safe)
                            ->orWhere('email', 'like', $safe);
                    });
            });
        }

        // Business type filter — mirrors the Business Types browse routes.
        if ($type = request('type')) {
            $query->where(function ($q) use ($type) {
                if ($type === 'shopping') {
                    $q->where('enabled_modules->catalog', true)->orWhere('enabled_modules->orders', true);
                } elseif ($type === 'booking') {
                    $q->where('enabled_modules->bookings', true);
                } elseif ($type === 'taxi') {
                    $q->where('enabled_modules->transport', true);
                } elseif ($type === 'directory') {
                    // Directory-only listings have no transactional modules enabled.
                    $q->where(function ($sub) {
                        $sub->whereNull('enabled_modules')
                            ->orWhere('enabled_modules', '[]')
                            ->orWhere('enabled_modules', '{}')
                            ->orWhereNot(function ($modules) {
                                $modules->where('enabled_modules->catalog', true)
                                    ->orWhere('enabled_modules->orders', true)
                                    ->orWhere('enabled_modules->bookings', true)
                                    ->orWhere('enabled_modules->transport', true);
                            });
                    });
                }
            });
        }

        // Sub-type = Directory category
        if ($categoryId = request('category_id')) {
            $query->where('category_id', $categoryId);
        }

        // Verification status (pending/verified/rejected)
        if ($status = request('verification_status')) {
            $query->where('verification_status', $status);
        }

        // Owner account status
        if (request('owner_status') === 'banned') {
            $query->whereHas('createdBy', fn ($q) => $q->whereNotNull('banned_at'));
        } elseif (request('owner_status') === 'active') {
            $query->whereHas('createdBy', fn ($q) => $q->whereNull('banned_at'));
        }

        $vendors = $query
            ->withCount(['products', 'services', 'bookings', 'orders', 'vehicles'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $categories = Category::where('is_canonical', true)->orderBy('name')->get(['id', 'name']);

        return view('admin.vendors.index', compact('vendors', 'categories'));
    })->name('vendors');

    // Business Owner detail — owner + business info, enabled modules, 30-day stats.
    Route::get('/vendors/business/{id}', function ($id) {
        $business = Business::with(['createdBy', 'category'])->findOrFail($id);
        abort_unless($business->created_by, 404);

        $since = now()->subDays(30);

        $stats = [
            'orders' => $business->orders()->where('created_at', '>=', $since)->count(),
            'bookings' => $business->bookings()->where('created_at', '>=', $since)->count(),
            'revenue' => (float) $business->orders()
                ->where('created_at', '>=', $since)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->sum('total'),
            'reviews_count' => $business->reviews()->count(),
            'rating' => round((float) $business->reviews()->avg('rating'), 1),
        ];

        $counts = [
            'products' => $business->products()->count(),
            'services' => $business->services()->count(),
            'vehicles' => $business->vehicles()->count(),
        ];

        if ($business->hasModule('transport')) {
            $typeLabel = 'Taxi';
        } elseif ($business->hasModule('bookings')) {
            $typeLabel = 'Booking';
        } elseif ($business->hasModule('orders') || $business->hasModule('catalog')) {
            $typeLabel = 'Shopping';
        } else {
            $typeLabel = 'Directory';
        }

        $modules = [
            'shopping' => $business->hasModule('catalog') || $business->hasModule('orders'),
            'booking' => $business->hasModule('bookings'),
            'taxi' => $business->hasModule('transport'),
        ];

        $recentOrders = $business->orders()->latest()->take(5)->get();

        return view('admin.vendors.business', compact('business', 'stats', 'counts', 'recentOrders', 'typeLabel', 'modules'));
    })->name('vendors.business');

    // Export current Business Owners filter as CSV.
    Route::get('/vendors/export', function () {
        $query = Business::query()
            ->with(['createdBy', 'category'])
            ->whereNotNull('created_by');

        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($safe) {
                $q->where('name', 'like', $safe)
                    ->orWhereHas('createdBy', function ($oq) use ($safe) {
                        $oq->where('name', 'like', $safe)->orWhere('email', 'like', $safe);
                    });
            });
        }

        if ($type = request('type')) {
            $query->where(function ($q) use ($type) {
                if ($type === 'shopping') {
                    $q->where('enabled_modules->catalog', true)->orWhere('enabled_modules->orders', true);
                } elseif ($type === 'booking') {
                    $q->where('enabled_modules->bookings', true);
                } elseif ($type === 'taxi') {
                    $q->where('enabled_modules->transport', true);
                } elseif ($type === 'directory') {
                    $q->where(function ($sub) {
                        $sub->whereNull('enabled_modules')
                            ->orWhere('enabled_modules', '[]')
                            ->orWhere('enabled_modules', '{}')
                            ->orWhereNot(function ($modules) {
                                $modules->where('enabled_modules->catalog', true)
                                    ->orWhere('enabled_modules->orders', true)
                                    ->orWhere('enabled_modules->bookings', true)
                                    ->orWhere('enabled_modules->transport', true);
                            });
                    });
                }
            });
        }

        if ($categoryId = request('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($status = request('verification_status')) {
            $query->where('verification_status', $status);
        }

        if (request('owner_status') === 'banned') {
            $query->whereHas('createdBy', fn ($q) => $q->whereNotNull('banned_at'));
        } elseif (request('owner_status') === 'active') {
            $query->whereHas('createdBy', fn ($q) => $q->whereNull('banned_at'));
        }

        $businesses = $query->orderBy('name')->get();

        $typeOf = function ($business) {
            if ($business->hasModule('transport')) {
                return 'Taxi';
            }
            if ($business->hasModule('bookings')) {
                return 'Booking';
            }
            if ($business->hasModule('orders') || $business->hasModule('catalog')) {
                return 'Shopping';
            }

            return 'Directory';
        };

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Owner', 'Owner Email', 'Owner Phone', 'Business', 'Type', 'Sub-Type', 'Verification', 'Owner Status', 'Address']);
        foreach ($businesses as $business) {
            fputcsv($handle, [
                $business->createdBy->name ?? '',
                $business->createdBy->email ?? '',
                $business->createdBy->phone ?? '',
                $business->name,
                $typeOf($business),
                $business->category->name ?? '',
                $business->verification_status,
                $business->createdBy ? ($business->createdBy->banned_at ? 'suspended' : 'active') : '',
                $business->address ?? '',
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="business-owners-'.now()->format('Y-m-d-His').'.csv"',
        ]);
    })->name('vendors.export');

    Route::get('/vendors/{id}', function ($id) {
        $vendor = User::where('role', 'owner')->findOrFail($id);
        $businesses = Business::where('created_by', $vendor->id)->with('category')->get();
        $recentActivity = ActivityLog::where('user_id', $vendor->id)->latest()->take(10)->get();

        return view('admin.vendors.show', compact('vendor', 'businesses', 'recentActivity'));
    })->name('vendors.show');

    // ─── Staff Management ─── super_admin only
    Route::get('/staff', function () {
        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator', 'manager'])->latest()->paginate(20)->withQueryString();

        return view('admin.staff.index', compact('staff'));
    })->name('staff')->middleware('admin:platform');

    Route::get('/staff/create', function () {
        return view('admin.staff.form', ['staff' => null]);
    })->name('staff.create');

    Route::post('/staff', function (Request $request) {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:moderator,manager,admin,super_admin',
            'department' => 'nullable|in:directory,shopping,booking,taxi,support',
            'is_active' => 'boolean',
        ]);

        // Hierarchy guard: you cannot create a staff member above your own level.
        $roleLevel = ['moderator' => 0, 'manager' => 1, 'admin' => 2, 'super_admin' => 3][$request->role];
        if (Auth::user()->staffLevel() < $roleLevel) {
            return back()->withErrors(['role' => 'You cannot create a staff member with a role above your own.'])->withInput();
        }
        if ($request->role === 'super_admin' && ! Auth::user()->isSuperAdmin()) {
            return back()->withErrors(['role' => 'Only a Super Admin can create another Super Admin.'])->withInput();
        }

        $staff = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => $request->role,
            'department' => $request->role === 'super_admin' ? null : $request->input('department'),
            'is_active' => $request->boolean('is_active', true),
            'created_by_admin' => Auth::id(),
        ]);

        ActivityLogService::log('staff_created', $staff, ['role' => $staff->role, 'department' => $staff->department]);

        return redirect()->route('admin.staff')->with('success', 'Staff member created.');
    })->name('staff.store');

    Route::get('/staff/{id}', function ($id) {
        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator', 'manager'])->findOrFail($id);

        return view('admin.staff.show', compact('staff'));
    })->name('staff.show');

    Route::get('/staff/{id}/edit', function ($id) {
        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator', 'manager'])->findOrFail($id);

        return view('admin.staff.form', compact('staff'));
    })->name('staff.edit');

    Route::put('/staff/{id}', function (Request $request, $id) {
        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator', 'manager'])->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$staff->id,
            'role' => 'required|in:moderator,manager,admin,super_admin',
            'department' => 'nullable|in:directory,shopping,booking,taxi,support',
            'is_active' => 'boolean',
        ]);

        // Hierarchy guard: you cannot elevate someone above your own level.
        $roleLevel = ['moderator' => 0, 'manager' => 1, 'admin' => 2, 'super_admin' => 3][$request->role];
        if (Auth::user()->staffLevel() < $roleLevel) {
            return back()->withErrors(['role' => 'You cannot assign a role above your own.'])->withInput();
        }
        if ($request->role === 'super_admin' && ! Auth::user()->isSuperAdmin()) {
            return back()->withErrors(['role' => 'Only a Super Admin can assign Super Admin.'])->withInput();
        }

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'department' => $request->role === 'super_admin' ? null : $request->input('department'),
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
        $staff = User::whereIn('role', ['super_admin', 'admin', 'moderator', 'manager'])->findOrFail($id);

        if ($staff->id === Auth::id()) {
            return back()->with('error', 'Cannot delete your own account.');
        }

        ActivityLogService::log('staff_deleted', $staff, ['name' => $staff->name]);
        $staff->delete();

        return redirect()->route('admin.staff')->with('success', 'Staff member deleted.');
    })->name('staff.destroy');

    // ─── Activity Logs ─── admin + super_admin
    Route::get('/activity-logs', function () {
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
    })->name('activity-logs')->middleware('admin:platform');

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
        $pinned = Setting::get('pinned_states', []);
        if (is_string($pinned)) {
            $pinned = json_decode($pinned, true) ?? [];
        }

        $states = Pincode::selectRaw('state, COUNT(*) as total, SUM(serviceable) as serviceable')
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
        $districts = Pincode::selectRaw('district, COUNT(*) as total, SUM(serviceable) as serviceable')
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
        $pincodes = Pincode::where('state', $state)
            ->where('district', $district)
            ->orderBy('pincode')
            ->paginate(50);
        $serviceableCount = $pincodes->total() > 0
            ? Pincode::where('state', $state)->where('district', $district)->where('serviceable', true)->count()
            : 0;

        return view('admin.pincodes.localities', compact('state', 'district', 'pincodes', 'serviceableCount'));
    })->name('pincodes.localities');

    Route::post('/pincodes/toggle-state', function (Request $request) {
        $state = $request->input('state');
        $enable = $request->boolean('enable');
        Pincode::where('state', $state)->update(['serviceable' => $enable]);

        return redirect()->route('admin.pincodes')->with('success', ($enable ? 'Enabled' : 'Disabled')." all pincodes in {$state}.");
    })->name('pincodes.toggle-state');

    Route::post('/pincodes/toggle-district', function (Request $request) {
        $state = $request->input('state');
        $district = $request->input('district');
        $enable = $request->boolean('enable');
        Pincode::where('state', $state)->where('district', $district)->update(['serviceable' => $enable]);

        return redirect()->back()->with('success', ($enable ? 'Enabled' : 'Disabled')." all pincodes in {$district}, {$state}.");
    })->name('pincodes.toggle-district');

    Route::post('/pincodes/toggle-pincode', function (Request $request) {
        $id = $request->input('id');
        $state = $request->input('state');
        $district = $request->input('district');
        $pincode = Pincode::findOrFail($id);
        $pincode->update(['serviceable' => ! $pincode->serviceable]);

        return redirect()->route('admin.pincodes.localities', [$state, $district])->with('success', "Pincode {$pincode->pincode} ".($pincode->serviceable ? 'enabled' : 'disabled').'.');
    })->name('pincodes.toggle-pincode');

    Route::post('/pincodes/toggle-pin', function (Request $request) {
        $state = $request->input('state');
        $pinned = Setting::get('pinned_states', []);
        if (is_string($pinned)) {
            $pinned = json_decode($pinned, true) ?? [];
        }

        if (in_array($state, $pinned)) {
            $pinned = array_values(array_filter($pinned, fn ($s) => $s !== $state));
            $message = "{$state} unpinned.";
        } else {
            $pinned[] = $state;
            $message = "{$state} pinned.";
        }

        Setting::set('pinned_states', json_encode(array_values($pinned)), 'general');

        return redirect()->route('admin.pincodes')->with('success', $message);
    })->name('pincodes.toggle-pin');

    // ─── Area Interest / Coming Soon Leads ───
    Route::get('/area-interests', function () {
        $interests = AreaInterest::latest()->paginate(50);

        return view('admin.area-interests', compact('interests'));
    })->name('area-interests');

    // ─── Bookings Management ───
    Route::get('/bookings', function () {
        $tab = in_array(request('tab'), ['all', 'appointments', 'stays', 'turf', 'seats'], true)
            ? request('tab')
            : 'all';

        $query = Booking::with(['business:id,name', 'service:id,name,booking_mode'])->latest('booking_date');

        if ($tab === 'appointments') {
            $query->where(function ($q) {
                $q->where('booking_type', 'standard')
                    ->orWhere(fn ($q2) => $q2->where('booking_type', 'time_slot')
                        ->whereHas('service', fn ($q3) => $q3->where('booking_mode', 'appointment')));
            });
        } elseif ($tab === 'stays') {
            $query->where('booking_type', 'stay');
        } elseif ($tab === 'turf') {
            $query->where('booking_type', 'time_slot')->whereHas('service', fn ($q) => $q->where('booking_mode', 'slot'));
        } elseif ($tab === 'seats') {
            $query->where('booking_type', 'seat');
        }

        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($safe) {
                $q->where('customer_name', 'like', $safe)
                    ->orWhere('customer_phone', 'like', $safe)
                    ->orWhere('client_reference', 'like', $safe);
            });
        }
        if ($status = request('status')) {
            $query->where('status', $status);
        }
        if ($paymentStatus = request('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }
        if ($date = request('date')) {
            $query->whereDate('booking_date', $date);
        }
        if ($businessId = request('business_id')) {
            $query->where('business_id', $businessId);
        }

        $bookings = $query->paginate(20)->withQueryString();

        $counts = [
            'all' => Booking::count(),
            'appointments' => Booking::where(function ($q) {
                $q->where('booking_type', 'standard')
                    ->orWhere(fn ($q2) => $q2->where('booking_type', 'time_slot')
                        ->whereHas('service', fn ($q3) => $q3->where('booking_mode', 'appointment')));
            })->count(),
            'stays' => Booking::where('booking_type', 'stay')->count(),
            'turf' => Booking::where('booking_type', 'time_slot')->whereHas('service', fn ($q) => $q->where('booking_mode', 'slot'))->count(),
            'seats' => Booking::where('booking_type', 'seat')->count(),
        ];

        $stats = [
            'pending' => Booking::where('status', 'pending')->count(),
            'confirmed' => Booking::where('status', 'confirmed')->count(),
            'completed' => Booking::where('status', 'completed')->count(),
            'paid' => Booking::where('payment_status', 'paid')->sum('total_price'),
            'unpaid' => Booking::where('payment_status', 'pending')->sum('total_price'),
        ];

        $businesses = Business::orderBy('name')->get(['id', 'name']);

        return view('admin.bookings.index', compact('tab', 'bookings', 'counts', 'stats', 'businesses'));
    })->name('bookings')->middleware('launch:world.book');

    Route::get('/bookings/{id}', function ($id) {
        $booking = Booking::with(['business', 'service', 'timeSlot'])
            ->findOrFail($id);

        return view('admin.bookings.show', compact('booking'));
    })->name('bookings.show')->middleware('launch:world.book');

    Route::put('/bookings/{id}/status', function (Request $request, $id) {
        $booking = Booking::with('business')->findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|in:confirmed,rejected,cancelled,completed,no_show',
            'reason' => 'nullable|string|max:500',
        ]);
        app(BookingWorkflowService::class)->transition($booking, $validated['status'], $validated['reason'] ?? null);

        return back()->with('success', 'Booking marked as '.$validated['status'].'.');
    })->name('bookings.status')->middleware('launch:world.book');

    Route::put('/bookings/{id}/payment-status', function (Request $request, $id) {
        $booking = Booking::with('business')->findOrFail($id);
        $request->validate(['payment_status' => 'required|in:paid']);
        app(BookingWorkflowService::class)->markCashCollected($booking);

        return back()->with('success', 'Cash payment marked as collected.');
    })->name('bookings.payment-status')->middleware('launch:world.book');

    Route::delete('/bookings/{id}', function ($id) {
        $booking = Booking::findOrFail($id);
        abort_unless(in_array($booking->status, ['pending', 'cancelled', 'rejected'], true), 422, 'Only pending, cancelled or rejected bookings can be deleted.');
        $booking->delete();

        return redirect()->route('admin.bookings')->with('success', 'Booking deleted.');
    })->name('bookings.destroy')->middleware('launch:world.book');

    // ─── Shopping Orders ───
    // Only shopping-module orders (the `orders` table), with shopping business
    // type tabs (Grocery, Food, Medicine, General Shopping).
    $shoppingOrdersIndex = function () {
        $shopWorld = World::where('slug', 'shop')->first();
        $businessTypes = $shopWorld
            ? Category::where('world_id', $shopWorld->id)
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('order')->orderBy('name')
                ->get()
            : collect();

        $allShopCategories = $shopWorld
            ? Category::where('world_id', $shopWorld->id)->get()->keyBy('id')
            : collect();
        $collectTypeIds = function (int $typeId, array &$ids) use (&$collectTypeIds, $allShopCategories): void {
            $ids[] = $typeId;
            foreach ($allShopCategories->where('parent_id', $typeId) as $child) {
                $collectTypeIds($child->id, $ids);
            }
        };

        $businessTypeId = (int) request('business_type');

        $query = Order::with(['business:id,name', 'items'])->latest();

        if ($businessTypeId) {
            $ids = [];
            $collectTypeIds($businessTypeId, $ids);
            $query->whereHas('business', function ($q) use ($ids) {
                $q->where(fn ($b) => $b->whereIn('category_id', $ids)
                    ->orWhereHas('classifications', fn ($c) => $c->whereIn('category_id', $ids)->where('is_active', true)));
            });
        }

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

        $typeCounts = [];
        foreach ($businessTypes as $type) {
            $ids = [];
            $collectTypeIds($type->id, $ids);
            $typeCounts[$type->id] = Order::whereHas('business', function ($q) use ($ids) {
                $q->where(fn ($b) => $b->whereIn('category_id', $ids)
                    ->orWhereHas('classifications', fn ($c) => $c->whereIn('category_id', $ids)->where('is_active', true)));
            })->count();
        }

        return view('admin.orders.index', compact('orders', 'businessTypes', 'businessTypeId', 'typeCounts'));
    };

    Route::get('/orders', fn () => $shoppingOrdersIndex())
        ->name('orders')->middleware('launch:world.shop');

    Route::delete('/orders/{id}', function ($id) {
        $order = Order::with('items')->findOrFail($id);
        app(OrderWorkflowService::class)->releaseInventory($order);
        $order->items()->delete();
        $order->delete();

        return redirect()->route('admin.orders')->with('success', 'Order deleted.');
    })->name('orders.destroy')->middleware('launch:world.shop');

    Route::get('/orders/{id}', function ($id) {
        $order = Order::with(['items.product', 'business:id,name', 'transactions'])->findOrFail($id);

        return view('admin.orders.show', compact('order'));
    })->name('orders.show')->whereNumber('id')->middleware('launch:world.shop');

    Route::put('/orders/{id}/status', function (Request $request, $id) {
        $order = Order::findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|string',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            app(OrderWorkflowService::class)->transition($order, $validated['status'], $validated['reason'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', $e->getMessage());
        }

        return redirect()->route('admin.orders.show', $order->id)->with('success', 'Order marked as '.$validated['status'].'.');
    })->name('orders.status')->whereNumber('id')->middleware('launch:world.shop');

    Route::put('/orders/{id}/payment-status', function (Request $request, $id) {
        $order = Order::findOrFail($id);
        $validated = $request->validate(['payment_status' => 'required|in:paid']);

        try {
            app(OrderWorkflowService::class)->markCashCollected($order);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', $e->getMessage());
        }

        return redirect()->route('admin.orders.show', $order->id)->with('success', 'Cash collected.');
    })->name('orders.payment-status')->whereNumber('id')->middleware('launch:world.shop');

    Route::put('/orders/{id}/refund', function (Request $request, $id) {
        $order = Order::findOrFail($id);
        $validated = $request->validate(['reason' => 'nullable|string|max:500']);

        try {
            app(OrderWorkflowService::class)->refund($order, $validated['reason'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', $e->getMessage());
        }

        return redirect()->route('admin.orders.show', $order->id)->with('success', 'Order refunded.');
    })->name('orders.refund')->whereNumber('id')->middleware('launch:world.shop');

    // ─── Universal Orders ───
    // Super-admin single page: aggregates shopping orders + bookings + trips
    // with module tabs (All / Shopping / Booking / Taxi) and shopping business
    // type tabs when Shopping is selected. Full access only.
    $universalOrdersIndex = function () {
        $module = in_array(request('module'), ['shopping', 'booking', 'taxi'], true) ? request('module') : 'all';
        $search = trim((string) request('search'));
        $status = request('status');
        $paymentStatus = request('payment_status');
        $businessTypeId = (int) request('business_type');

        $shopWorld = World::where('slug', 'shop')->first();
        $businessTypes = $shopWorld
            ? Category::where('world_id', $shopWorld->id)
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->orderBy('order')->orderBy('name')
                ->get()
            : collect();

        $allShopCategories = $shopWorld
            ? Category::where('world_id', $shopWorld->id)->get()->keyBy('id')
            : collect();
        $collectTypeIds = function (int $typeId, array &$ids) use (&$collectTypeIds, $allShopCategories): void {
            $ids[] = $typeId;
            foreach ($allShopCategories->where('parent_id', $typeId) as $child) {
                $collectTypeIds($child->id, $ids);
            }
        };

        $records = collect();

        if ($module === 'all' || $module === 'shopping') {
            $q = Order::with(['business:id,name'])->latest();
            if ($search) {
                $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where(fn ($b) => $b->where('customer_name', 'like', $safe)->orWhere('order_number', 'like', $safe));
            }
            if ($status) {
                $q->where('status', $status);
            }
            if ($paymentStatus) {
                $q->where('payment_status', $paymentStatus);
            }
            if ($businessTypeId) {
                $ids = [];
                $collectTypeIds($businessTypeId, $ids);
                $q->whereHas('business', fn ($b) => $b->where(fn ($x) => $x->whereIn('category_id', $ids)
                    ->orWhereHas('classifications', fn ($c) => $c->whereIn('category_id', $ids)->where('is_active', true))));
            }

            foreach ($q->get() as $order) {
                $records->push([
                    'type' => 'shopping',
                    'type_label' => 'Shopping',
                    'reference' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'business' => $order->business?->name,
                    'detail' => $order->items()->count().' item(s)',
                    'amount' => (float) $order->total,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'created_at' => $order->created_at,
                    'delete_url' => route('admin.orders.destroy', $order->id),
                ]);
            }
        }

        if ($module === 'all' || $module === 'booking') {
            $q = Booking::with(['business:id,name', 'service:id,name'])->latest();
            if ($search) {
                $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where(fn ($b) => $b->where('customer_name', 'like', $safe)->orWhere('customer_phone', 'like', $safe));
            }
            if ($status) {
                $q->where('status', $status);
            }
            if ($paymentStatus) {
                $q->where('payment_status', $paymentStatus);
            }

            foreach ($q->get() as $booking) {
                $records->push([
                    'type' => 'booking',
                    'type_label' => 'Booking',
                    'reference' => '#'.$booking->id,
                    'customer_name' => $booking->customer_name,
                    'customer_phone' => $booking->customer_phone,
                    'business' => $booking->business?->name,
                    'detail' => $booking->service?->name ?? 'Service booking',
                    'amount' => (float) $booking->total_price,
                    'status' => $booking->status,
                    'payment_status' => $booking->payment_status,
                    'created_at' => $booking->created_at,
                    'delete_url' => route('admin.bookings.destroy', $booking->id),
                ]);
            }
        }

        if ($module === 'all' || $module === 'taxi') {
            $q = Trip::with(['business:id,name', 'vehicle:id,name'])->latest();
            if ($search) {
                $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where(fn ($b) => $b->where('customer_name', 'like', $safe)->orWhere('customer_phone', 'like', $safe)->orWhere('pickup_location', 'like', $safe));
            }
            if ($status) {
                $q->where('status', $status);
            }
            if ($paymentStatus) {
                $q->where('payment_status', $paymentStatus);
            }

            foreach ($q->get() as $trip) {
                $records->push([
                    'type' => 'taxi',
                    'type_label' => 'Taxi',
                    'reference' => '#'.$trip->id,
                    'customer_name' => $trip->customer_name,
                    'customer_phone' => $trip->customer_phone,
                    'business' => $trip->business?->name,
                    'detail' => trim(($trip->vehicle?->name ?? 'Vehicle').' · '.($trip->pickup_location ?? '?').' → '.($trip->drop_location ?? '?')),
                    'amount' => (float) $trip->fare,
                    'status' => $trip->status,
                    'payment_status' => $trip->payment_status,
                    'created_at' => $trip->created_at,
                    'delete_url' => route('admin.trips.destroy', $trip->id),
                ]);
            }
        }

        $total = $records->count();
        $perPage = 20;
        $page = Paginator::resolveCurrentPage('page');
        $records = new LengthAwarePaginator(
            $records->sortByDesc('created_at')->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()]
        );

        $typeCounts = [];
        foreach ($businessTypes as $type) {
            $ids = [];
            $collectTypeIds($type->id, $ids);
            $typeCounts[$type->id] = Order::whereHas('business', fn ($b) => $b->where(fn ($x) => $x->whereIn('category_id', $ids)
                ->orWhereHas('classifications', fn ($c) => $c->whereIn('category_id', $ids)->where('is_active', true))))->count();
        }

        $moduleCounts = [
            'shopping' => Order::count(),
            'booking' => Booking::count(),
            'taxi' => Trip::count(),
        ];

        return view('admin.orders.universal', compact(
            'records', 'module', 'businessTypes', 'businessTypeId', 'typeCounts', 'moduleCounts'
        ));
    };

    Route::get('/orders/universal', fn () => $universalOrdersIndex())
        ->name('orders.universal');

    // ─── Admin Trips (Taxi / Transport) ───
    // Unified Transport Bookings hub — one page with tabs for Trips,
    // Seat Bookings and Vehicle Hire (mirrors the Shopping Orders pattern).
    Route::get('/transport-bookings', function () {
        $tab = in_array(request('tab'), ['trips', 'seat_bookings', 'vehicle_hire'], true) ? request('tab') : 'trips';

        $records = collect();
        if ($tab === 'trips') {
            $q = Trip::with(['business:id,name', 'vehicle:id,name'])->latest();
            if ($status = request('status')) {
                $q->where('status', $status);
            }
            $records = $q->paginate(10)->withQueryString();
        } elseif ($tab === 'seat_bookings') {
            $q = ScheduleBooking::with(['business:id,name', 'schedule.vehicle:id,name,type,image'])->latest();
            if ($status = request('status')) {
                $q->where('status', $status);
            }
            $records = $q->paginate(10)->withQueryString();
        } elseif ($tab === 'vehicle_hire') {
            $q = VehicleRental::with(['business:id,name', 'vehicle:id,name,type,image'])->latest();
            if ($status = request('status')) {
                $q->where('status', $status);
            }
            $records = $q->paginate(10)->withQueryString();
        }

        $counts = [
            'trips' => Trip::count(),
            'seat_bookings' => ScheduleBooking::count(),
            'vehicle_hire' => VehicleRental::count(),
        ];

        return view('admin.transport-bookings.index', compact('tab', 'counts', 'records'));
    })->name('transport-bookings')->middleware('launch:world.ride');

    Route::get('/trips', function () {
        $query = Trip::with(['business:id,name', 'vehicle:id,name'])->latest();

        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function ($q) use ($safe) {
                $q->where('customer_name', 'like', $safe)
                    ->orWhere('customer_phone', 'like', $safe)
                    ->orWhere('pickup_location', 'like', $safe);
            });
        }
        if ($status = request('status')) {
            $query->where('status', $status);
        }
        if ($paymentStatus = request('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        $trips = $query->paginate(20)->withQueryString();

        return view('admin.trips.index', compact('trips'));
    })->name('trips')->middleware('launch:world.ride');

    Route::delete('/trips/{id}', function ($id) {
        Trip::findOrFail($id)->delete();

        return redirect()->route('admin.trips')->with('success', 'Trip deleted.');
    })->name('trips.destroy')->middleware('launch:world.ride');

    Route::put('/trips/{id}/status', function (Request $request, $id) {
        $trip = Trip::with('business')->findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|in:confirmed,started,completed,cancelled',
            'cancellation_reason' => 'nullable|string|max:500',
            'driver_name' => 'nullable|string|max:255',
            'driver_phone' => 'nullable|string|max:20',
        ]);
        app(TripWorkflowService::class)->transition(
            $trip,
            $validated['status'],
            $validated['cancellation_reason'] ?? null,
            ['name' => $validated['driver_name'] ?? null, 'phone' => $validated['driver_phone'] ?? null],
        );

        return back()->with('success', 'Transport request '.$validated['status'].'.');
    })->name('trips.status')->middleware('launch:world.ride');

    Route::put('/trips/{id}/quote', function (Request $request, $id) {
        $trip = Trip::with('business')->findOrFail($id);
        $validated = $request->validate([
            'fare' => 'required|numeric|min:0|max:10000000',
            'quote_notes' => 'nullable|string|max:1000',
        ]);
        app(TripWorkflowService::class)->quote($trip, (float) $validated['fare'], $validated['quote_notes'] ?? null);

        return back()->with('success', 'Fare quote saved.');
    })->name('trips.quote')->middleware('launch:world.ride');

    Route::put('/trips/{id}/payment-status', function (Request $request, $id) {
        $trip = Trip::with('business')->findOrFail($id);
        $request->validate(['payment_status' => 'required|in:paid']);
        app(TripWorkflowService::class)->markCashCollected($trip);

        return back()->with('success', 'Cash payment marked as collected.');
    })->name('trips.payment-status')->middleware('launch:world.ride');

    // ─── Reviews Moderation ───
    Route::get('/reviews', function () {
        $query = Review::with(['user:id,name', 'business:id,name'])->latest();

        if ($status = request('status')) {
            $query->where('status', $status);
        }
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
        $counts = [
            'all' => Review::count(),
            'pending' => Review::where('status', 'pending')->count(),
            'approved' => Review::where('status', 'approved')->count(),
            'hidden' => Review::where('status', 'hidden')->count(),
        ];

        return view('admin.reviews.index', compact('reviews', 'counts'));
    })->name('reviews');

    Route::put('/reviews/{id}/moderate', function (Request $request, $id) {
        $validated = $request->validate([
            'status' => 'required|in:approved,pending,hidden',
            'reason' => 'nullable|string|max:500',
        ]);
        $review = Review::findOrFail($id);
        $review->update([
            'status' => $validated['status'],
            'moderation_reason' => $validated['reason'] ?? null,
            'flagged_at' => $validated['status'] === 'hidden' ? ($review->flagged_at ?? now()) : $review->flagged_at,
        ]);
        ActivityLogService::log('review_moderated', $review, [
            'business_id' => $review->business_id,
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.reviews', request()->query())->with('success', 'Review '.$validated['status'].'.');
    })->name('reviews.moderate');

    Route::delete('/reviews/{id}', function ($id) {
        Review::findOrFail($id)->delete();

        return redirect()->route('admin.reviews')->with('success', 'Review deleted.');
    })->name('reviews.destroy');

    // ─── Business Photos Gallery ───
    Route::get('/gallery', function () {
        $query = MediaLibrary::with('business:id,name')->latest();

        if ($status = request('status')) {
            $query->where('status', $status);
        }
        if ($businessId = request('business_id')) {
            $query->where('business_id', $businessId);
        }
        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(fn ($q) => $q->where('original_filename', 'like', $safe)
                ->orWhere('alt_text', 'like', $safe)
                ->orWhereHas('business', fn ($b) => $b->where('name', 'like', $safe)));
        }

        $media = $query->paginate(24)->withQueryString();
        $counts = [
            'all' => MediaLibrary::count(),
            'approved' => MediaLibrary::where('status', 'approved')->count(),
            'pending' => MediaLibrary::where('status', 'pending')->count(),
            'hidden' => MediaLibrary::where('status', 'hidden')->count(),
        ];
        $businesses = Business::select('id', 'name')->orderBy('name')->get();

        return view('admin.gallery.index', compact('media', 'counts', 'businesses'));
    })->name('gallery');

    Route::put('/gallery/{id}/moderate', function (Request $request, $id) {
        $validated = $request->validate([
            'status' => 'required|in:approved,pending,hidden',
            'reason' => 'nullable|string|max:500',
        ]);
        $media = MediaLibrary::findOrFail($id);
        $media->update([
            'status' => $validated['status'],
            'moderation_reason' => $validated['reason'] ?? null,
            'flagged_at' => $validated['status'] === 'hidden' ? ($media->flagged_at ?? now()) : $media->flagged_at,
        ]);
        ActivityLogService::log('media_moderated', $media, [
            'business_id' => $media->business_id,
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Photo '.$validated['status'].'.');
    })->name('gallery.moderate');

    Route::put('/gallery/{id}/detail', function (Request $request, $id) {
        $validated = $request->validate([
            'alt_text' => 'nullable|string|max:255',
            'sort_order' => 'required|integer|min:0|max:9999',
            'is_cover' => 'required|boolean',
        ]);
        $media = MediaLibrary::findOrFail($id);

        if ($validated['is_cover']) {
            MediaLibrary::where('business_id', $media->business_id)->where('is_cover', true)->where('id', '!=', $media->id)->update(['is_cover' => false]);
        }
        $media->update($validated);

        return back()->with('success', 'Photo updated.');
    })->name('gallery.detail');

    Route::delete('/gallery/{id}', function ($id) {
        $media = MediaLibrary::findOrFail($id);
        if ($media->path && Storage::disk($media->disk)->exists($media->path)) {
            Storage::disk($media->disk)->delete($media->path);
        }
        $media->delete();

        return back()->with('success', 'Photo deleted.');
    })->name('gallery.destroy');

    // ─── Services Management ───
    Route::get('/services', function () {
        $query = Service::with(['business:id,name', 'bookings', 'business.category'])->orderBy('name');

        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where('name', 'like', $safe);
        }
        if ($businessId = request('business_id')) {
            $query->where('business_id', $businessId);
        }
        if ($bookingMode = request('booking_mode')) {
            $query->where('booking_mode', $bookingMode);
        }
        if ($categoryId = request('category_id')) {
            $query->whereHas('business', fn ($q) => $q->where('category_id', $categoryId));
        }
        if (request()->has('is_active') && request('is_active') !== '') {
            $query->where('is_active', request('is_active') === '1');
        }

        $services = $query->paginate(20)->withQueryString();

        $groupCounts = Service::with('business.category')
            ->get()
            ->groupBy(fn ($service) => $service->business?->category?->name ?? 'Uncategorised')
            ->map->count()
            ->sortDesc();

        $modeCounts = [
            'appointment' => Service::where('booking_mode', 'appointment')->count(),
            'stay' => Service::where('booking_mode', 'stay')->count(),
            'slot' => Service::where('booking_mode', 'slot')->count(),
            'seat' => Service::where('booking_mode', 'seat')->count(),
        ];

        return view('admin.services.index', compact('services', 'modeCounts', 'groupCounts'));
    })->name('services')->middleware('launch:world.book');

    Route::get('/services/{id}/edit', function ($id) {
        $service = Service::with('business')->findOrFail($id);

        return view('admin.services.form', compact('service'));
    })->name('services.edit')->middleware('launch:world.book');

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
    })->name('services.update')->middleware('launch:world.book');

    Route::delete('/services/{id}', function ($id) {
        Service::findOrFail($id)->delete();

        return redirect()->route('admin.services')->with('success', 'Service deleted.');
    })->name('services.destroy')->middleware('launch:world.book');

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
    })->name('transactions')->middleware('admin:platform');

    // ─── Integration API Keys ───
    Route::get('/integration-keys', function () {
        $keys = IntegrationApiKey::orderBy('created_at', 'desc')->paginate(20);

        return view('admin.integration-keys.index', compact('keys'));
    })->name('integration-keys');

    Route::post('/integration-keys/generate', function (Request $request) {
        $allowedTenants = ['hola', 'ai_agent', 'shop'];
        $allowedScopes = [
            '*',
            'businesses:read',
            'businesses:read,leads:read,leads:write',
            'products:read,products:write,orders:read,orders:write',
            'bookings:read,bookings:write,services:read,services:write',
        ];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tenant_type' => 'nullable|string|max:50',
            'scopes' => 'nullable|string|max:500',
        ]);

        $tenantType = $validated['tenant_type'] ?? 'hola';
        abort_unless(in_array($tenantType, $allowedTenants, true), 422, 'Invalid API key purpose.');
        abort_unless(in_array($validated['scopes'] ?? '*', $allowedScopes, true), 422, 'Invalid access level.');

        $scopes = ($validated['scopes'] ?? '*') === '*' ? ['*'] : explode(',', $validated['scopes']);

        $rawKey = 'ehl_'.Str::random(48);
        $hash = hash('sha256', $rawKey);

        $key = IntegrationApiKey::create([
            'name' => $validated['name'],
            'key_hash' => $hash,
            'key_prefix' => substr($rawKey, 0, 10),
            'tenant_type' => $tenantType,
            'scopes' => $scopes,
            'is_revoked' => false,
        ]);

        return redirect()->route('admin.integration-keys')
            ->with('new_key', $rawKey)
            ->with('success', 'API key generated successfully.');
    })->name('integration-keys.generate');

    Route::post('/integration-keys/{id}/revoke', function ($id) {
        $key = IntegrationApiKey::findOrFail($id);
        $key->update(['is_revoked' => true, 'revoked_at' => now()]);

        return redirect()->route('admin.integration-keys')
            ->with('success', 'API key revoked.');
    })->name('integration-keys.revoke');

    // Customer-facing app features: intentionally limited to the five umbrella switches.
    Route::get('/feature-flags', function () {
        $masterFeatures = [
            'world.shop' => ['name' => 'Shopping', 'description' => 'All shops, menus, products, and COD order requests.'],
            'world.book' => ['name' => 'Bookings', 'description' => 'All appointments, hotels, turfs, and other booking requests.'],
            'world.ride' => ['name' => 'Transport', 'description' => 'All taxi, shared ride, vehicle rental, and goods transport requests.'],
            'world.discover' => ['name' => 'Directory', 'description' => 'Business listings, contact details, and map discovery.'],
        ];
        $flags = FeatureFlag::whereIn('key', array_keys($masterFeatures))
            ->get()
            ->sortBy(fn ($flag) => array_search($flag->key, array_keys($masterFeatures), true))
            ->values();

        return view('admin.feature-flags.index', compact('flags', 'masterFeatures'));
    })->name('feature-flags')->middleware('admin:platform');

    Route::post('/feature-flags', function (Request $request) {
        $validated = $request->validate([
            'key' => 'required|string|unique:feature_flags,key',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'group' => 'required|string',
            'launch_phase' => 'nullable|string',
            'is_enabled' => 'boolean',
            'is_visible_to_customers' => 'boolean',
        ]);

        $validated['is_enabled'] = $request->boolean('is_enabled');
        $validated['is_visible_to_customers'] = $request->boolean('is_visible_to_customers');

        FeatureFlag::create($validated);

        return redirect()->route('admin.feature-flags')->with('success', 'Feature flag created.');
    })->name('feature-flags.store');

    Route::patch('/feature-flags/{id}/toggle', function ($id) {
        $flag = FeatureFlag::findOrFail($id);
        $flag->update(['is_enabled' => ! $flag->is_enabled]);

        return redirect()->route('admin.feature-flags')
            ->with('success', $flag->name.' '.($flag->is_enabled ? 'enabled' : 'disabled').'.');
    })->name('feature-flags.toggle');

    Route::delete('/feature-flags/{id}', function ($id) {
        $flag = FeatureFlag::findOrFail($id);
        abort_if((bool) data_get($flag->metadata, 'system'), 422, 'Platform launch controls cannot be deleted.');
        $flag->delete();

        return redirect()->route('admin.feature-flags')->with('success', 'Feature flag deleted.');
    })->name('feature-flags.destroy');

    // Directory Category Manager
    // Shows ONLY the AI/imported business classifications (is_canonical=true).
    // Shop product categories live in their own taxonomy (shop_sections +
    // product_categories) and are managed under the Shop department.
    Route::get('/category-tree', function () {
        $categories = Category::with(['children' => function ($q) {
            $q->active()->ordered()->with('children');
        }])
            ->where('is_canonical', true)
            ->root()
            ->active()
            ->ordered()
            ->get();

        return view('admin.categories.tree', compact('categories'));
    })->name('category-tree');

    Route::post('/category-tree', function (Request $request) {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'module_type' => 'required|in:directory,ordering,booking',
            'launch_phase' => 'required|string',
            'is_active' => 'boolean',
            'show_on_home' => 'boolean',
            'is_featured' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['show_on_home'] = $request->boolean('show_on_home');
        $validated['is_featured'] = $request->boolean('is_featured');
        $validated['is_canonical'] = true;

        // Shared write-path: the world is derived from the classification bucket,
        // not chosen by hand. A child inherits its parent's world.
        Category::applyTaxonomy($validated, $validated['parent_id'] ?? null);

        Category::create($validated);

        return redirect()->route('admin.category-tree')->with('success', 'Directory category created.');
    })->name('category-tree.store');

    Route::patch('/category-tree/{id}/toggle', function ($id) {
        $cat = Category::findOrFail($id);
        $cat->update(['is_active' => ! $cat->is_active]);

        return redirect()->route('admin.category-tree')
            ->with('success', $cat->name.' '.($cat->is_active ? 'activated' : 'deactivated').'.');
    })->name('category-tree.toggle');

    // Shop Sections — global storefront sections, independent from Directory taxonomy.
    Route::get('/shop-sections', function () {
        $sections = ShopSection::withCount('productCategories')->ordered()->get();

        return view('admin.shop-sections.index', compact('sections'));
    })->name('shop-sections')->middleware('launch:world.shop');

    Route::post('/shop-sections', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = true;

        ShopSection::create($validated);

        return redirect()->route('admin.shop-sections')->with('success', 'Shop section created.');
    })->name('shop-sections.store')->middleware('launch:world.shop');

    Route::patch('/shop-sections/{id}/toggle', function ($id) {
        $section = ShopSection::findOrFail($id);
        $section->update(['is_active' => ! $section->is_active]);

        return redirect()->route('admin.shop-sections')
            ->with('success', $section->name.' '.($section->is_active ? 'activated' : 'deactivated').'.');
    })->name('shop-sections.toggle')->middleware('launch:world.shop');

    Route::delete('/shop-sections/{id}', function ($id) {
        $section = ShopSection::findOrFail($id);
        $section->delete();

        return redirect()->route('admin.shop-sections')->with('success', 'Shop section deleted.');
    })->name('shop-sections.destroy')->middleware('launch:world.shop');

    // Product Categories — admin taxonomy grouped per shopping business type,
    // with optional sub-categories (parent_id). No per-business coupling.
    Route::get('/product-categories', function (Request $request) {
        $shopWorld = World::where('slug', 'shop')->first();
        $businessTypes = $shopWorld
            ? Category::where('world_id', $shopWorld->id)
                ->whereNull('parent_id')
                ->orderBy('order')->orderBy('name')
                ->get()
            : collect();
        $type = $businessTypes->firstWhere('id', $request->query('business_type_id'))
            ?? $businessTypes->first();

        $categories = $type
            ? ProductCategory::where('business_type_id', $type->id)
                ->with('section')
                ->withCount('products')
                ->orderBy('sort_order')->orderBy('name')
                ->get()
            : collect();

        $sections = ShopSection::active()->ordered()->get();

        return view('admin.product-categories.index', compact('businessTypes', 'type', 'categories', 'sections'));
    })->name('product-categories')->middleware('launch:world.shop');

    Route::post('/product-categories', function (Request $request) {
        $validated = $request->validate([
            'business_type_id' => 'required|exists:categories,id',
            'shop_section_id' => 'nullable|exists:shop_sections,id',
            'parent_id' => 'nullable|exists:product_categories,id',
            'name' => 'required|string|max:255',
        ]);

        if (! empty($validated['parent_id'])) {
            $parent = ProductCategory::find($validated['parent_id']);
            abort_unless($parent && $parent->business_type_id === (int) $validated['business_type_id'], 422);
        }

        $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(4);
        $validated['is_active'] = true;

        ProductCategory::create($validated);

        return redirect()->route('admin.product-categories', ['business_type_id' => $validated['business_type_id']])
            ->with('success', 'Product category created.');
    })->name('product-categories.store')->middleware('launch:world.shop');

    Route::patch('/product-categories/{id}', function (Request $request, $id) {
        $category = ProductCategory::findOrFail($id);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'shop_section_id' => 'nullable|exists:shop_sections,id',
            'parent_id' => 'nullable|exists:product_categories,id',
            'is_active' => 'boolean',
        ]);

        if (! empty($validated['parent_id'])) {
            $parent = ProductCategory::find($validated['parent_id']);
            abort_unless($parent && $parent->business_type_id === $category->business_type_id, 422);
        }

        $validated['is_active'] = $request->boolean('is_active');
        $category->update($validated);

        return redirect()->route('admin.product-categories', ['business_type_id' => $category->business_type_id])
            ->with('success', 'Product category updated.');
    })->name('product-categories.update')->middleware('launch:world.shop');

    Route::delete('/product-categories/{id}', function ($id) {
        $category = ProductCategory::findOrFail($id);
        $businessTypeId = $category->business_type_id;
        $category->delete();

        return redirect()->route('admin.product-categories', ['business_type_id' => $businessTypeId])
            ->with('success', 'Product category deleted.');
    })->name('product-categories.destroy')->middleware('launch:world.shop');

    // Vehicle Types — global transport types (car, truck, bus…). Vendors pick from these.
    Route::get('/vehicle-types', function () {
        $types = VehicleType::withCount('vehicles')->ordered()->get();

        return view('admin.vehicle-types.index', compact('types'));
    })->name('vehicle-types')->middleware('launch:world.ride');

    Route::post('/vehicle-types', function (Request $request) {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:60|regex:/^[a-z0-9\-]+$/|unique:vehicle_types,slug',
            'description' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);
        $validated['is_active'] = true;

        VehicleType::create($validated);

        return redirect()->route('admin.vehicle-types')->with('success', 'Vehicle type created.');
    })->name('vehicle-types.store')->middleware('launch:world.ride');

    Route::patch('/vehicle-types/{id}/toggle', function ($id) {
        $type = VehicleType::findOrFail($id);
        $type->update(['is_active' => ! $type->is_active]);

        return redirect()->route('admin.vehicle-types')
            ->with('success', $type->name.' '.($type->is_active ? 'activated' : 'deactivated').'.');
    })->name('vehicle-types.toggle')->middleware('launch:world.ride');

    Route::delete('/vehicle-types/{id}', function ($id) {
        $type = VehicleType::findOrFail($id);
        if ($type->vehicles()->exists()) {
            return redirect()->route('admin.vehicle-types')
                ->with('error', 'Cannot delete "'.$type->name.'" — it is used by '.$type->vehicles()->count().' vehicle(s). Deactivate it instead.');
        }
        $type->delete();

        return redirect()->route('admin.vehicle-types')->with('success', 'Vehicle type deleted.');
    })->name('vehicle-types.destroy')->middleware('launch:world.ride');

    // ─── Transport Routes (admin-curated route suggestions) ───
    Route::get('/transport-routes', function () {
        $routes = TransportRoute::withCount('schedules')->ordered()->paginate(30)->withQueryString();

        return view('admin.transport-routes.index', compact('routes'));
    })->name('transport-routes')->middleware('launch:world.ride');

    Route::post('/transport-routes', function (Request $request) {
        $validated = $request->validate([
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'distance_km' => 'nullable|numeric|min:0.1',
            'base_fare' => 'nullable|numeric|min:0',
            'estimated_minutes' => 'nullable|integer|min:1',
        ]);

        $origin = trim($validated['origin']);
        $destination = trim($validated['destination']);

        if (strcasecmp($origin, $destination) === 0) {
            return back()->withErrors(['destination' => 'Origin and destination must be different.'])->withInput();
        }

        $attributes = [
            'distance_km' => $validated['distance_km'] ?? null,
            'base_fare' => $validated['base_fare'] ?? null,
            'estimated_minutes' => $validated['estimated_minutes'] ?? null,
            'is_active' => true,
            'sort_order' => 0,
        ];

        // Always create both directions so customers can search either way.
        $forward = TransportRoute::firstOrCreate(
            ['origin' => $origin, 'destination' => $destination],
            $attributes,
        );
        $reverse = TransportRoute::firstOrCreate(
            ['origin' => $destination, 'destination' => $origin],
            $attributes,
        );

        if (! $forward->wasRecentlyCreated && ! $reverse->wasRecentlyCreated) {
            return redirect()->route('admin.transport-routes')
                ->with('info', 'Route already exists in both directions.');
        }

        return redirect()->route('admin.transport-routes')->with('success', "Transport route added ({$origin} ↔ {$destination}).");
    })->name('transport-routes.store')->middleware('launch:world.ride');

    Route::get('/transport-routes/{id}/edit', function ($id) {
        $route = TransportRoute::findOrFail($id);

        return view('admin.transport-routes.form', compact('route'));
    })->name('transport-routes.edit')->middleware('launch:world.ride');

    Route::put('/transport-routes/{id}', function (Request $request, $id) {
        $route = TransportRoute::findOrFail($id);
        $validated = $request->validate([
            'origin' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'distance_km' => 'nullable|numeric|min:0.1',
            'base_fare' => 'nullable|numeric|min:0',
            'estimated_minutes' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $origin = trim($validated['origin']);
        $destination = trim($validated['destination']);

        if (strcasecmp($origin, $destination) === 0) {
            return back()->withErrors(['destination' => 'Origin and destination must be different.'])->withInput();
        }

        $validated['origin'] = $origin;
        $validated['destination'] = $destination;
        $validated['is_active'] = $request->boolean('is_active');

        // Capture the previous pair before updating so the reverse stays in sync.
        $oldOrigin = $route->getOriginal('origin');
        $oldDestination = $route->getOriginal('destination');
        $pairChanged = $oldOrigin !== $origin || $oldDestination !== $destination;

        $route->update($validated);

        // Remove the old reverse when the pair changed, then (re)create the
        // reverse for the current pair.
        if ($pairChanged && $oldOrigin && $oldDestination) {
            TransportRoute::where('origin', $oldDestination)
                ->where('destination', $oldOrigin)
                ->delete();
        }

        TransportRoute::updateOrCreate(
            ['origin' => $destination, 'destination' => $origin],
            [
                'distance_km' => $validated['distance_km'] ?? null,
                'base_fare' => $validated['base_fare'] ?? null,
                'estimated_minutes' => $validated['estimated_minutes'] ?? null,
                'is_active' => $validated['is_active'],
                'sort_order' => 0,
            ],
        );

        return redirect()->route('admin.transport-routes')->with('success', "Transport route updated ({$origin} ↔ {$destination}).");
    })->name('transport-routes.update')->middleware('launch:world.ride');

    Route::patch('/transport-routes/{id}/toggle', function ($id) {
        $route = TransportRoute::findOrFail($id);
        $route->update(['is_active' => ! $route->is_active]);

        return redirect()->route('admin.transport-routes')
            ->with('success', "Route {$route->origin} → {$route->destination} ".($route->is_active ? 'activated' : 'deactivated').'.');
    })->name('transport-routes.toggle')->middleware('launch:world.ride');

    Route::delete('/transport-routes/{id}', function ($id) {
        $route = TransportRoute::findOrFail($id);
        if ($route->schedules()->where('status', 'scheduled')->exists()) {
            return redirect()->route('admin.transport-routes')
                ->with('error', 'Cannot delete a route with active schedules. Deactivate it instead.');
        }
        $route->delete();

        return redirect()->route('admin.transport-routes')->with('success', 'Transport route deleted.');
    })->name('transport-routes.destroy')->middleware('launch:world.ride');

    // ─── Seat Bookings (all transport seat bookings across vendors) ───
    Route::get('/seat-bookings', function () {
        $query = ScheduleBooking::with(['business:id,name', 'schedule.vehicle:id,name,type,image'])
            ->latest();

        if ($status = request('status')) {
            $query->where('status', $status);
        }
        if ($date = request('date')) {
            $query->whereHas('schedule', fn ($q) => $q->whereDate('departure_date', $date));
        }
        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(fn ($q) => $q->where('customer_name', 'like', $safe)->orWhere('customer_phone', 'like', $safe));
        }

        $bookings = $query->paginate(20)->withQueryString();

        return view('admin.seat-bookings.index', compact('bookings'));
    })->name('seat-bookings')->middleware('launch:world.ride');

    Route::delete('/seat-bookings/{id}', function ($id) {
        ScheduleBooking::findOrFail($id)->delete();

        return redirect()->route('admin.seat-bookings')->with('success', 'Seat booking deleted.');
    })->name('seat-bookings.destroy')->middleware('launch:world.ride');

    Route::put('/seat-bookings/{id}/status', function (Request $request, $id) {
        $booking = ScheduleBooking::with('business')->findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|in:confirmed,completed,cancelled,no_show',
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        match ($validated['status']) {
            'confirmed' => $booking->markConfirmed(),
            'completed' => $booking->markCompleted(),
            'no_show' => $booking->markNoShow(),
            'cancelled' => $booking->markCancelled($validated['cancellation_reason'] ?? null),
        };

        return back()->with('success', 'Seat booking '.$validated['status'].'.');
    })->name('seat-bookings.status')->middleware('launch:world.ride');

    Route::put('/seat-bookings/{id}/payment-status', function (Request $request, $id) {
        $booking = ScheduleBooking::findOrFail($id);
        $request->validate(['payment_status' => 'required|in:paid']);
        if ($booking->status === 'cancelled') {
            throw Illuminate\Validation\ValidationException::withMessages(['payment_status' => 'A cancelled seat booking cannot be marked as paid.']);
        }
        $booking->update(['payment_status' => 'paid', 'payment_method' => 'cash']);

        return back()->with('success', 'Cash payment marked as collected.');
    })->name('seat-bookings.payment-status')->middleware('launch:world.ride');

    // ─── Vehicle Hire / Rentals (all hire bookings across vendors) ───
    Route::get('/vehicle-rentals', function () {
        $query = VehicleRental::with(['business:id,name', 'vehicle:id,name,type,image'])
            ->latest();

        if ($status = request('status')) {
            $query->where('status', $status);
        }
        if ($search = request('search')) {
            $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(fn ($q) => $q->where('customer_name', 'like', $safe)->orWhere('customer_phone', 'like', $safe));
        }

        $rentals = $query->paginate(20)->withQueryString();

        return view('admin.vehicle-rentals.index', compact('rentals'));
    })->name('vehicle-rentals')->middleware('launch:world.ride');

    Route::delete('/vehicle-rentals/{id}', function ($id) {
        VehicleRental::findOrFail($id)->delete();

        return redirect()->route('admin.vehicle-rentals')->with('success', 'Vehicle hire deleted.');
    })->name('vehicle-rentals.destroy')->middleware('launch:world.ride');

    Route::put('/vehicle-rentals/{id}/status', function (Request $request, $id) {
        $rental = VehicleRental::with('business')->findOrFail($id);
        $validated = $request->validate([
            'status' => 'required|in:confirmed,completed,cancelled',
            'cancellation_reason' => 'nullable|string|max:500',
        ]);

        match ($validated['status']) {
            'confirmed' => $rental->markConfirmed(),
            'completed' => $rental->markCompleted(),
            'cancelled' => $rental->markCancelled($validated['cancellation_reason'] ?? null),
        };

        return back()->with('success', 'Vehicle hire '.$validated['status'].'.');
    })->name('vehicle-rentals.status')->middleware('launch:world.ride');

    Route::put('/vehicle-rentals/{id}/payment-status', function (Request $request, $id) {
        $rental = VehicleRental::findOrFail($id);
        $request->validate(['payment_status' => 'required|in:paid']);
        if ($rental->status === 'cancelled') {
            throw Illuminate\Validation\ValidationException::withMessages(['payment_status' => 'A cancelled hire cannot be marked as paid.']);
        }
        $rental->update(['payment_status' => 'paid', 'payment_method' => 'cash']);

        return back()->with('success', 'Cash payment marked as collected.');
    })->name('vehicle-rentals.payment-status')->middleware('launch:world.ride');

    // Homepage CMS
    Route::get('/homepage', [HomepageContentController::class, 'index'])->name('homepage');
    Route::get('/homepage/create', [HomepageContentController::class, 'create'])->name('homepage.create');
    Route::post('/homepage', [HomepageContentController::class, 'store'])->name('homepage.store');
    Route::get('/homepage/{content}/edit', [HomepageContentController::class, 'edit'])->name('homepage.edit');
    Route::put('/homepage/{content}', [HomepageContentController::class, 'update'])->name('homepage.update');
    Route::delete('/homepage/{content}', [HomepageContentController::class, 'destroy'])->name('homepage.destroy');
    Route::post('/homepage/reorder', [HomepageContentController::class, 'reorder'])->name('homepage.reorder');

    // Capability Templates
    Route::get('/capability-templates', [CapabilityTemplateController::class, 'index'])->name('capability-templates');
    Route::post('/capability-templates', [CapabilityTemplateController::class, 'store'])->name('capability-templates.store');
    Route::get('/capability-templates/{template}', [CapabilityTemplateController::class, 'show'])->name('capability-templates.show');
    Route::put('/capability-templates/{template}', [CapabilityTemplateController::class, 'update'])->name('capability-templates.update');
    Route::delete('/capability-templates/{template}', [CapabilityTemplateController::class, 'destroy'])->name('capability-templates.destroy');
    Route::post('/capability-templates/{template}/assign', [CapabilityTemplateController::class, 'assign'])->name('capability-templates.assign');
    Route::post('/capability-templates/{template}/revoke', [CapabilityTemplateController::class, 'revoke'])->name('capability-templates.revoke');

    // Classification Audit
    Route::get('/classification-audit', [ClassificationAuditController::class, 'index'])->name('classification-audit');
    Route::post('/classification-audit/fix', [ClassificationAuditController::class, 'fix'])->name('classification-audit.fix');
    Route::get('/classification-audit/stats', [ClassificationAuditController::class, 'stats'])->name('classification-audit.stats');
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
    })->middleware('throttle:5,1')->name('login.post');

    Route::post('/logout', function () {
        Auth::logout();

        return redirect()->route('vendor.login');
    })->name('logout');

    // Protected vendor routes
    Route::middleware(['auth', 'vendor.world'])->group(function () {
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

            $defaultBusinessId = $businesses->first()->id ?? null;
            $hasOrders = $businesses->contains(fn ($business) => $business->hasModule('orders'));
            $hasBookings = $businesses->contains(fn ($business) => $business->hasModule('bookings'));
            $hasProducts = $hasOrders;
            $stats = [
                'businesses' => $businesses->count(),
                'active_bookings' => Booking::whereIn('business_id', $businesses->pluck('id'))->whereIn('status', ['pending', 'confirmed'])->count(),
                'pending_orders' => Order::whereIn('business_id', $businesses->pluck('id'))->where('status', 'pending')->count(),
                'products' => $totalProducts,
            ];

            $vendorSetup = $defaultBusinessId
                ? VendorSetup::syncFromBusiness($businesses->first())
                : null;

            return view('vendor.dashboard.index', compact(
                'user', 'businesses', 'totalBookings', 'totalOrders', 'totalProducts',
                'recentBookings', 'recentOrders', 'stats', 'defaultBusinessId',
                'hasOrders', 'hasBookings', 'hasProducts', 'vendorSetup'
            ));
        })->name('dashboard');

        // Setup wizard redirect — first business with no modules configured
        Route::middleware('auth')->group(function () {
            Route::get('/setup', function () {
                $user = Auth::user();
                $business = Business::where('created_by', $user->id)
                    ->where(function ($q) {
                        $q->whereNull('enabled_modules')
                            ->orWhere('enabled_modules', '[]')
                            ->orWhere('enabled_modules', '{}');
                    })
                    ->first();

                if (! $business) {
                    return redirect()->route('vendor.dashboard');
                }

                return redirect()->route('vendor.businesses.setup', $business->id);
            })->name('setup.redirect');
        });

        // My Businesses
        Route::get('/businesses', function () {
            $user = Auth::user();
            $businesses = Business::where('created_by', $user->id)
                ->with('category:id,name')->latest()->paginate(20);

            return view('vendor.businesses.index', compact('businesses'));
        })->name('businesses');

        Route::get('/businesses/create', function () {
            $categories = Category::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id']);
            $cities = City::active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'state']);

            return view('vendor.businesses.create', compact('categories', 'cities'));
        })->name('businesses.create');

        Route::post('/businesses', function (Request $request) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'category_id' => 'required|exists:categories,id',
                'city_id' => 'nullable|exists:cities,id',
                'address' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'whatsapp' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255',
                'description' => 'nullable|string',
            ]);

            $business = Business::create(array_merge($validated, [
                // Phase 6 lifecycle: self-created listings are owned by the
                // vendor and must pass admin verification before going live.
                'slug' => Str::slug($request->name),
                'created_by' => Auth::id(),
                'claim_status' => 'claimed',
                'verification_status' => 'pending',
                'source' => 'vendor',
                'is_active' => true,
            ]));
            $business->syncPrimaryClassification($validated['category_id'], 'vendor_created');
            ActivityLogService::log('business_created', $business, ['business_id' => $business->id, 'source' => 'vendor_self_create']);

            return redirect()->route('vendor.businesses.setup', $business->id)
                ->with('success', 'Business created! Tell us what you offer to finish setup.');
        })->name('businesses.store');

        Route::get('/businesses/{id}/edit', function ($id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($id);

            return view('vendor.businesses.form', compact('business'));
        })->name('businesses.edit');

        Route::put('/businesses/{id}', function (Request $request, $id) {
            $business = Business::findOrFail($id);
            Gate::authorize('update', $business);
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
                $decoded = json_decode($request->working_hours, true);
                if (is_array($decoded)) {
                    $validated['working_hours'] = BusinessHours::validateSchedule($decoded)['hours'];
                } else {
                    unset($validated['working_hours']);
                }
            } else {
                unset($validated['working_hours']);
            }
            $business->update($validated);

            return redirect()->route('vendor.businesses')->with('success', 'Business updated.');
        })->name('businesses.update');

        Route::get('/businesses/{id}/modules', function ($id) {
            $business = Business::where('created_by', Auth::id())
                ->with(['category', 'subcategory'])
                ->findOrFail($id);
            $moduleService = app(BusinessModuleService::class);

            return view('vendor.businesses.modules', [
                'business' => $business,
                'definitions' => BusinessModuleService::DEFINITIONS,
                'modules' => $moduleService->effectiveFor($business),
                'recommended' => $moduleService->recommendedFor($business),
                'readiness' => $moduleService->readiness($business),
                'globallyEnabledModules' => app(LaunchControlService::class)->enabledModuleKeys(),
            ]);
        })->name('businesses.modules');

        Route::put('/businesses/{id}/modules', function (Request $request, $id) {
            $business = Business::where('created_by', Auth::id())->findOrFail($id);

            if ($business->verification_status !== 'verified') {
                return redirect()->route('vendor.businesses.modules', $business->id)
                    ->with('error', 'Your business is pending admin verification. You can enable features once it is verified.');
            }

            $validated = $request->validate([
                'modules' => 'nullable|array',
                'modules.*' => 'in:catalog,orders,bookings,inventory,transport,turf',
                'tax_percent' => 'nullable|numeric|min:0|max:100',
                'discount_amount' => 'nullable|numeric|min:0|max:10000000',
            ]);

            app(BusinessModuleService::class)->update($business, $validated['modules'] ?? []);

            $business->update([
                'tax_percent' => $validated['tax_percent'] ?? 0,
                'discount_amount' => $validated['discount_amount'] ?? 0,
            ]);

            return redirect()->route('vendor.businesses.modules', $business->id)
                ->with('success', 'Business features updated. Required dependencies were enabled automatically.');
        })->name('businesses.modules.update');

        // Vendor subscription / monetization
        Route::get('/businesses/{id}/subscription', function ($id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->with(['subscription.plan'])->findOrFail($id);
            $plans = SubscriptionPlan::active()->orderBy('price')->get();
            $monetization = app(MonetizationService::class);

            return view('vendor.businesses.subscription', compact('business', 'plans', 'monetization'));
        })->name('businesses.subscription');

        Route::post('/businesses/{id}/subscription', function (Request $request, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($id);
            $validated = $request->validate(['plan_id' => 'required|exists:subscription_plans,id']);
            $plan = SubscriptionPlan::findOrFail($validated['plan_id']);
            app(MonetizationService::class)->subscribe($business, $plan);

            return back()->with('success', "Subscribed to {$plan->name}. Contact admin to confirm payment.");
        })->name('businesses.subscription.store');

        // Setup Wizard
        Route::get('/businesses/{id}/setup', function ($id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($id);
            $moduleService = app(BusinessModuleService::class);
            $modules = $moduleService->effectiveFor($business);
            $templates = CapabilityTemplate::active()->get()->keyBy('slug');
            $suggestion = app(BookingTypeResolver::class)->setupFor($business);

            return view('vendor.businesses.setup', compact('business', 'modules', 'templates', 'suggestion'));
        })->name('businesses.setup');

        Route::post('/businesses/{id}/setup', function (Request $request, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($id);

            // Phase 6 lifecycle: a business must be admin-verified before it can
            // become a working vendor (enable sell/book modules).
            if ($business->verification_status !== 'verified') {
                return redirect()->route('vendor.dashboard')
                    ->with('error', 'Your business is pending admin verification. Once verified, you can choose what you offer.');
            }

            $validated = $request->validate([
                'offer' => 'required|in:sell,book,list',
            ]);

            $templateSlug = match ($validated['offer']) {
                'sell' => 'retail',
                'book' => 'bookings',
                'list' => 'general',
            };

            if ($validated['offer'] === 'book') {
                // Category-matched booking setup: a salon, hotel, turf, clinic or
                // electrician gets the experience that fits its business type.
                $resolver = app(BookingTypeResolver::class);
                $setup = $resolver->setupFor($business);
                app(BusinessModuleService::class)->update($business, $setup['modules']);
                $business->forceFill([
                    'enabled_experiences' => $setup['experiences'],
                    'primary_experience' => $setup['experience'],
                ])->save();
                $experienceService = app(BusinessExperienceService::class);
                foreach ($setup['experiences'] as $exp) {
                    $experienceService->setAvailabilityMode($business, $exp, 'request');
                }

                return redirect()->route('vendor.dashboard')
                    ->with('success', 'Your business is set up to take bookings. Add your services next.');
            }

            $template = CapabilityTemplate::where('slug', $templateSlug)->first();
            if ($template) {
                $template->applyTo($business);
            } else {
                // Templates are seeded in production; this fallback mirrors the
                // seeded templates so the offer screen works on any database state.
                $presets = [
                    'sell' => [
                        'modules' => ['catalog' => true, 'orders' => true, 'inventory' => true],
                        'experiences' => ['retail', 'directory'],
                        'availability' => 'request',
                    ],
                    'list' => [
                        'modules' => [],
                        'experiences' => ['directory'],
                        'availability' => 'contact',
                    ],
                ];
                $preset = $presets[$validated['offer']];
                app(BusinessModuleService::class)->update($business, $preset['modules']);
                $business->forceFill([
                    'enabled_experiences' => $preset['experiences'],
                    'primary_experience' => collect($preset['experiences'])->first(fn ($experience) => $experience !== 'directory') ?? 'directory',
                ])->save();
                $experienceService = app(BusinessExperienceService::class);
                foreach ($preset['experiences'] as $exp) {
                    $experienceService->setAvailabilityMode($business, $exp, $preset['availability']);
                }
            }

            return redirect()->route('vendor.dashboard')
                ->with('success', 'Your business is set up! You can customize features anytime in Business Features.');
        })->name('businesses.setup.post');

        // The 9-step wizard is retired — one "What do you offer?" screen
        // replaces it. Any leftover links land on that screen.
        Route::get('/onboarding/{id}/step/{step}', function ($id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($id);

            return redirect()->route('vendor.businesses.setup', $business->id);
        })->name('onboarding.step');

        Route::post('/onboarding/{id}/step/{step}', function ($id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($id);

            return redirect()->route('vendor.businesses.setup', $business->id);
        })->name('onboarding.store');

        // Products
        Route::get('/businesses/{businessId}/products', function ($businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('catalog'), 404);
            $products = Product::where('business_id', $business->id)->latest()->paginate(20);

            return view('vendor.products.index', compact('products', 'business'));
        })->name('products');

        Route::get('/businesses/{businessId}/products/create', function ($businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('catalog'), 404);

            $categories = ProductCategory::where('business_id', $business->id)
                ->with('section')
                ->active()
                ->orderBy('sort_order')->orderBy('name')
                ->get();

            return view('vendor.products.form', compact('business', 'categories'));
        })->name('products.create');

        Route::post('/businesses/{businessId}/products', function (Request $request, $businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('catalog'), 404);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'product_category_id' => 'nullable|exists:product_categories,id',
                'menu_section' => 'nullable|string|max:100',
                'food_type' => 'nullable|in:veg,non_veg,egg,vegan,other',
                'preparation_minutes' => 'nullable|integer|min:1|max:1440',
                'available_from' => 'nullable|required_with:available_until|date_format:H:i',
                'available_until' => 'nullable|required_with:available_from|date_format:H:i',
                'sold_out_until' => 'nullable|date|after:now',
                'price' => 'nullable|numeric|min:0',
                'stock' => 'nullable|integer|min:0',
                'availability' => 'nullable|in:in_stock,out_of_stock,limited',
                'is_active' => 'nullable|boolean',
            ]);
            if (! empty($validated['product_category_id'])
                && ! ProductCategory::where('id', $validated['product_category_id'])
                    ->where('business_id', $business->id)->exists()) {
                return back()->withErrors(['product_category_id' => 'Selected category does not belong to this business.']);
            }
            $validated['business_id'] = $business->id;
            $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);
            $validated['is_active'] = $request->has('is_active');
            Product::create($validated);

            return redirect()->route('vendor.products', $business->id)->with('success', 'Product created.');
        })->name('products.store');

        Route::get('/businesses/{businessId}/products/{id}/edit', function ($businessId, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('catalog'), 404);
            $product = Product::where('business_id', $business->id)->findOrFail($id);

            $categories = ProductCategory::where('business_id', $business->id)
                ->with('section')
                ->active()
                ->orderBy('sort_order')->orderBy('name')
                ->get();

            return view('vendor.products.form', compact('business', 'product', 'categories'));
        })->name('products.edit');

        Route::put('/businesses/{businessId}/products/{id}', function (Request $request, $businessId, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('catalog'), 404);
            $product = Product::where('business_id', $business->id)->findOrFail($id);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'product_category_id' => 'nullable|exists:product_categories,id',
                'menu_section' => 'nullable|string|max:100',
                'food_type' => 'nullable|in:veg,non_veg,egg,vegan,other',
                'preparation_minutes' => 'nullable|integer|min:1|max:1440',
                'available_from' => 'nullable|required_with:available_until|date_format:H:i',
                'available_until' => 'nullable|required_with:available_from|date_format:H:i',
                'sold_out_until' => 'nullable|date|after:now',
                'price' => 'nullable|numeric|min:0',
                'stock' => 'nullable|integer|min:0',
                'availability' => 'nullable|in:in_stock,out_of_stock,limited',
                'is_active' => 'nullable|boolean',
            ]);
            if (! empty($validated['product_category_id'])
                && ! ProductCategory::where('id', $validated['product_category_id'])
                    ->where('business_id', $business->id)->exists()) {
                return back()->withErrors(['product_category_id' => 'Selected category does not belong to this business.']);
            }
            $validated['is_active'] = $request->has('is_active');
            $product->update($validated);

            return redirect()->route('vendor.products', $business->id)->with('success', 'Product updated.');
        })->name('products.update');

        Route::delete('/businesses/{businessId}/products/{id}', function ($businessId, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('catalog'), 404);
            Product::where('business_id', $business->id)->findOrFail($id)->delete();

            return redirect()->route('vendor.products', $business->id)->with('success', 'Product deleted.');
        })->name('products.destroy');

        // Services
        Route::get('/businesses/{businessId}/services', function ($businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);
            $services = Service::where('business_id', $business->id)
                ->withCount(['bookings', 'timeSlots' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('sort_order')->paginate(20);

            return view('vendor.services.index', compact('services', 'business'));
        })->name('services');

        Route::get('/businesses/{businessId}/services/create', function ($businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);

            return view('vendor.services.form', compact('business'));
        })->name('services.create');

        Route::post('/businesses/{businessId}/services', function (Request $request, $businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);

            // Plan gate: Free tier is capped at 5 active services.
            if (! app(PlanGate::class)->canAddService($business)) {
                return back()->withErrors(['services' => 'Your plan allows '.app(PlanGate::class)->maxActiveServices($business).' services. Upgrade to add more.'])->withInput();
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'size_label' => 'nullable|string|max:60',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
                'price' => 'required|numeric|min:0',
                'booking_mode' => 'required|in:appointment,slot,stay,seat',
                'price_unit' => 'required|in:booking,hour,night,person,seat',
                'duration' => 'nullable|integer|min:15|max:1440',
                'capacity' => 'nullable|integer|min:1',
                'inventory_units' => 'nullable|integer|min:1|max:10000',
                'unit_label' => 'nullable|string|max:40',
                'check_in_time' => 'nullable|date_format:H:i',
                'check_out_time' => 'nullable|date_format:H:i',
                'min_stay_nights' => 'nullable|integer|min:1|max:365',
                'max_stay_nights' => 'nullable|integer|min:1|max:365|gte:min_stay_nights',
                'advance_booking_days' => 'nullable|integer|min:1|max:365',
                'cancellation_hours' => 'nullable|integer|min:0|max:168',
                'is_active' => 'nullable|boolean',
            ]);
            $validated['business_id'] = $business->id;
            $validated['has_fixed_slots'] = in_array($validated['booking_mode'], ['slot', 'seat'], true);
            $validated['duration'] = $validated['duration'] ?? 60;
            $validated['inventory_units'] = $validated['inventory_units'] ?? 1;
            $validated['slug'] = Str::slug($validated['name']).'-'.Str::random(5);
            $validated['is_active'] = $request->has('is_active');

            if ($request->hasFile('image')) {
                $filename = 'services/'.Str::slug($validated['name']).'-'.Str::random(5).'.'.$request->file('image')->getClientOriginalExtension();
                Storage::disk('public')->put($filename, file_get_contents($request->file('image')));
                $validated['image'] = 'storage/'.$filename;
            }

            Service::create($validated);

            return redirect()->route('vendor.services', $business->id)->with('success', 'Service created.');
        })->name('services.store');

        Route::get('/businesses/{businessId}/services/{id}/edit', function ($businessId, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);
            $service = Service::where('business_id', $business->id)->findOrFail($id);

            return view('vendor.services.form', compact('business', 'service'));
        })->name('services.edit');

        Route::put('/businesses/{businessId}/services/{id}', function (Request $request, $businessId, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);
            $service = Service::where('business_id', $business->id)->findOrFail($id);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'size_label' => 'nullable|string|max:60',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
                'price' => 'required|numeric|min:0',
                'booking_mode' => 'required|in:appointment,slot,stay,seat',
                'price_unit' => 'required|in:booking,hour,night,person,seat',
                'duration' => 'nullable|integer|min:15|max:1440',
                'capacity' => 'nullable|integer|min:1',
                'inventory_units' => 'nullable|integer|min:1|max:10000',
                'unit_label' => 'nullable|string|max:40',
                'check_in_time' => 'nullable|date_format:H:i',
                'check_out_time' => 'nullable|date_format:H:i',
                'min_stay_nights' => 'nullable|integer|min:1|max:365',
                'max_stay_nights' => 'nullable|integer|min:1|max:365|gte:min_stay_nights',
                'advance_booking_days' => 'nullable|integer|min:1|max:365',
                'cancellation_hours' => 'nullable|integer|min:0|max:168',
                'is_active' => 'nullable|boolean',
            ]);
            $validated['is_active'] = $request->has('is_active');
            $validated['has_fixed_slots'] = in_array($validated['booking_mode'], ['slot', 'seat'], true);
            $validated['duration'] = $validated['duration'] ?? 60;
            $validated['inventory_units'] = $validated['inventory_units'] ?? 1;

            if ($request->hasFile('image')) {
                $filename = 'services/'.Str::slug($validated['name']).'-'.Str::random(5).'.'.$request->file('image')->getClientOriginalExtension();
                Storage::disk('public')->put($filename, file_get_contents($request->file('image')));
                $validated['image'] = 'storage/'.$filename;
                if ($service->image) {
                    Storage::disk('public')->delete(str_replace('storage/', '', $service->image));
                }
            }

            $service->update($validated);

            return redirect()->route('vendor.services', $business->id)->with('success', 'Service updated.');
        })->name('services.update');

        Route::delete('/businesses/{businessId}/services/{id}', function ($businessId, $id) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);
            Service::where('business_id', $business->id)->findOrFail($id)->delete();

            return redirect()->route('vendor.services', $business->id)->with('success', 'Service deleted.');
        })->name('services.destroy');

        Route::get('/businesses/{businessId}/services/{serviceId}/slots', function ($businessId, $serviceId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            abort_unless(in_array($service->booking_mode, ['slot', 'seat']), 404);
            $slots = $service->timeSlots()->orderByRaw('day_of_week is null desc')->orderBy('day_of_week')->orderBy('start_time')->get();

            return view('vendor.services.slots', compact('business', 'service', 'slots'));
        })->name('services.slots');

        Route::post('/businesses/{businessId}/services/{serviceId}/slots', function (Request $request, $businessId, $serviceId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $validated = $request->validate([
                'day_of_week' => 'nullable|integer|between:0,6',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'capacity' => 'required|integer|min:1|max:10000',
                'price_override' => 'nullable|numeric|min:0',
            ]);
            $validated['service_id'] = $service->id;
            $validated['is_active'] = true;
            TimeSlot::create($validated);

            return back()->with('success', 'Time slot added.');
        })->name('services.slots.store');

        Route::put('/businesses/{businessId}/services/{serviceId}/slots/{slotId}', function (Request $request, $businessId, $serviceId, $slotId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $slot = TimeSlot::where('service_id', $service->id)->findOrFail($slotId);
            $validated = $request->validate([
                'day_of_week' => 'nullable|integer|between:0,6',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'capacity' => 'required|integer|min:1|max:10000',
                'price_override' => 'nullable|numeric|min:0',
                'is_active' => 'nullable|boolean',
            ]);
            $validated['is_active'] = $request->has('is_active');
            $slot->update($validated);

            return back()->with('success', 'Time slot updated.');
        })->name('services.slots.update');

        Route::delete('/businesses/{businessId}/services/{serviceId}/slots/{slotId}', function ($businessId, $serviceId, $slotId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $slot = TimeSlot::where('service_id', $service->id)->findOrFail($slotId);
            abort_if($slot->bookings()->whereIn('status', ['pending', 'confirmed'])->exists(), 422, 'Cannot delete a slot with active bookings.');
            $slot->delete();

            return back()->with('success', 'Time slot deleted.');
        })->name('services.slots.destroy');

        // Bookable resources + availability rules (modern slot generation)
        Route::get('/businesses/{businessId}/services/{serviceId}/resources', function ($businessId, $serviceId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $resources = $service->resources()->with('availabilityRules')->orderBy('sort_order')->get();

            return view('vendor.services.resources', compact('business', 'service', 'resources'));
        })->name('services.resources');

        Route::post('/businesses/{businessId}/services/{serviceId}/resources', function (Request $request, $businessId, $serviceId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'resource_type' => 'nullable|string|max:60',
                'capacity' => 'required|integer|min:1|max:1000',
                'description' => 'nullable|string|max:1000',
            ]);
            $validated['resource_type'] = $validated['resource_type'] ?? $service->booking_mode;
            $service->resources()->create($validated);

            return back()->with('success', 'Resource added.');
        })->name('services.resources.store');

        Route::put('/businesses/{businessId}/services/{serviceId}/resources/{resourceId}', function (Request $request, $businessId, $serviceId, $resourceId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $resource = $service->resources()->findOrFail($resourceId);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'resource_type' => 'nullable|string|max:60',
                'capacity' => 'required|integer|min:1|max:1000',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'nullable|boolean',
            ]);
            $validated['is_active'] = $request->has('is_active');
            $resource->update($validated);

            return back()->with('success', 'Resource updated.');
        })->name('services.resources.update');

        Route::delete('/businesses/{businessId}/services/{serviceId}/resources/{resourceId}', function ($businessId, $serviceId, $resourceId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $resource = $service->resources()->findOrFail($resourceId);
            $resource->availabilityRules()->delete();
            $resource->delete();

            return back()->with('success', 'Resource deleted.');
        })->name('services.resources.destroy');

        Route::post('/businesses/{businessId}/services/{serviceId}/resources/{resourceId}/rules', function (Request $request, $businessId, $serviceId, $resourceId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $resource = $service->resources()->findOrFail($resourceId);
            $validated = $request->validate([
                'day_of_week' => 'nullable|integer|between:0,6',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i',
                'slot_duration_minutes' => 'required|integer|min:5|max:1440',
                'buffer_minutes' => 'nullable|integer|min:0|max:1440',
                'capacity' => 'nullable|integer|min:1|max:1000',
                'booking_window_days' => 'nullable|integer|min:1|max:365',
                'minimum_notice_hours' => 'nullable|integer|min:0|max:168',
                'blackout_dates' => 'nullable|string',
            ]);
            $validated['buffer_minutes'] = $validated['buffer_minutes'] ?? 0;
            $validated['booking_window_days'] = $validated['booking_window_days'] ?? 30;
            $validated['minimum_notice_hours'] = $validated['minimum_notice_hours'] ?? 0;
            $validated['blackout_dates'] = $validated['blackout_dates'] ? array_values(array_filter(array_map('trim', explode(',', $validated['blackout_dates'])))) : [];
            $resource->availabilityRules()->create($validated);

            return back()->with('success', 'Availability rule added.');
        })->name('services.resources.rules.store');

        Route::put('/businesses/{businessId}/services/{serviceId}/resources/{resourceId}/rules/{ruleId}', function (Request $request, $businessId, $serviceId, $resourceId, $ruleId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $resource = $service->resources()->findOrFail($resourceId);
            $rule = $resource->availabilityRules()->findOrFail($ruleId);
            $validated = $request->validate([
                'day_of_week' => 'nullable|integer|between:0,6',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i',
                'slot_duration_minutes' => 'required|integer|min:5|max:1440',
                'buffer_minutes' => 'nullable|integer|min:0|max:1440',
                'capacity' => 'nullable|integer|min:1|max:1000',
                'booking_window_days' => 'nullable|integer|min:1|max:365',
                'minimum_notice_hours' => 'nullable|integer|min:0|max:168',
                'blackout_dates' => 'nullable|string',
                'is_active' => 'nullable|boolean',
            ]);
            $validated['buffer_minutes'] = $validated['buffer_minutes'] ?? 0;
            $validated['booking_window_days'] = $validated['booking_window_days'] ?? 30;
            $validated['minimum_notice_hours'] = $validated['minimum_notice_hours'] ?? 0;
            $validated['blackout_dates'] = $validated['blackout_dates'] ? array_values(array_filter(array_map('trim', explode(',', $validated['blackout_dates'])))) : [];
            $validated['is_active'] = $request->has('is_active');
            $rule->update($validated);

            return back()->with('success', 'Availability rule updated.');
        })->name('services.resources.rules.update');

        Route::delete('/businesses/{businessId}/services/{serviceId}/resources/{resourceId}/rules/{ruleId}', function ($businessId, $serviceId, $resourceId, $ruleId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $resource = $service->resources()->findOrFail($resourceId);
            $rule = $resource->availabilityRules()->findOrFail($ruleId);
            $rule->delete();

            return back()->with('success', 'Availability rule deleted.');
        })->name('services.resources.rules.destroy');

        // Bookings
        Route::get('/businesses/{businessId}/bookings', function ($businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);
            $query = Booking::where('business_id', $business->id)->with('service:id,name')->latest('booking_date');

            if ($status = request('status')) {
                $query->where('status', $status);
            }
            if ($date = request('date')) {
                $query->whereDate('booking_date', $date);
            }
            if ($search = request('search')) {
                $safe = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(function ($searchQuery) use ($safe) {
                    $searchQuery->where('customer_name', 'like', $safe)
                        ->orWhere('customer_phone', 'like', $safe);
                });
            }

            $bookings = $query->paginate(20)->withQueryString();

            return view('vendor.bookings.index', compact('bookings', 'business'));
        })->name('bookings');

        Route::get('/businesses/{businessId}/calendar', function ($businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);

            $month = request('month') ? Carbon::parse(request('month'))->startOfMonth() : now()->startOfMonth();
            $from = $month->copy()->startOfWeek();
            $to = $month->copy()->endOfMonth()->endOfWeek();

            $bookings = Booking::with('service:id,name')
                ->where('business_id', $business->id)
                ->whereDate('booking_date', '>=', $from->toDateString())
                ->whereDate('booking_date', '<=', $to->toDateString())
                ->orderBy('booking_date')
                ->get()
                ->groupBy(fn ($booking) => $booking->booking_date?->toDateString() ?? '');

            $weeks = [];
            $cursor = $from->copy();
            while ($cursor->lte($to)) {
                $week = [];
                foreach (range(0, 6) as $_) {
                    $date = $cursor->copy();
                    $week[] = [
                        'date' => $date,
                        'bookings' => $bookings->get($date->toDateString(), collect()),
                    ];
                    $cursor->addDay();
                }
                $weeks[] = $week;
            }

            return view('vendor.bookings.calendar', compact(
                'business', 'month', 'weeks', 'from', 'to'
            ));
        })->name('calendar');

        Route::put('/bookings/{id}/status', function (Request $request, $id) {
            $booking = Booking::with('business')->findOrFail($id);
            $user = Auth::user();
            abort_unless($booking->business->hasModule('bookings'), 404);
            if ($booking->business->created_by !== $user->id) {
                abort(403);
            }

            $validated = $request->validate([
                'status' => 'required|in:confirmed,cancelled,completed,no_show,rejected',
                'cancellation_reason' => 'nullable|string|max:500',
            ]);
            app(BookingWorkflowService::class)->transition(
                $booking,
                $validated['status'],
                $validated['cancellation_reason'] ?? null,
            );

            return back()->with('success', 'Booking '.$validated['status'].'.');
        })->name('bookings.status');

        Route::put('/bookings/{id}/reschedule', function (Request $request, $id) {
            $booking = Booking::with('business')->findOrFail($id);
            $user = Auth::user();
            abort_unless($booking->business->hasModule('bookings'), 404);
            abort_unless($booking->business->created_by === $user->id, 403);
            abort_unless(in_array($booking->status, ['pending', 'confirmed'], true), 422, 'Only pending or confirmed bookings can be rescheduled.');

            $validated = $request->validate([
                'rescheduled_to_date' => 'required|date|after_or_equal:today',
                'rescheduled_to_time' => 'nullable|date_format:H:i',
                'time_slot_id' => 'nullable|exists:time_slots,id',
                'reschedule_reason' => 'nullable|string|max:500',
            ]);

            app(BookingWorkflowService::class)->reschedule(
                $booking,
                $validated['rescheduled_to_date'],
                $validated['rescheduled_to_time'] ?? null,
                $validated['time_slot_id'] ?? null,
                $validated['reschedule_reason'] ?? null,
            );

            return back()->with('success', 'Booking rescheduled.');
        })->name('bookings.reschedule');

        Route::put('/bookings/{id}/payment-status', function (Request $request, $id) {
            $booking = Booking::with('business')->findOrFail($id);
            $user = Auth::user();
            abort_unless($booking->business->hasModule('bookings'), 404);
            abort_unless($booking->business->created_by === $user->id, 403);
            $request->validate(['payment_status' => 'required|in:paid']);

            app(BookingWorkflowService::class)->markCashCollected($booking);

            return back()->with('success', 'Cash payment marked as collected.');
        })->name('bookings.payment-status');

        // Stay check-in / check-out board
        Route::get('/businesses/{businessId}/stay-board', function ($businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);
            $today = today()->toDateString();

            $stays = Booking::with('service:id,name')
                ->where('business_id', $business->id)
                ->where('booking_type', 'stay')
                ->where(function ($query) use ($today) {
                    $query->where(function ($q) use ($today) {
                        $q->whereDate('check_in_date', '<=', $today)->whereDate('check_out_date', '>=', $today);
                    })->orWhere(function ($q) {
                        $q->where('checked_in_at', '!=', null);
                    });
                })
                ->orderByRaw('COALESCE(checked_in_at, check_in_date) asc')
                ->get();

            $arrivals = $stays->filter(fn ($b) => $b->check_in_date->isToday() && ! $b->checked_in_at && $b->status === 'confirmed');
            $inHouse = $stays->filter(fn ($b) => $b->checked_in_at && ! $b->checked_out_at);
            $departures = $stays->filter(fn ($b) => $b->check_out_date->isToday() && $b->checked_in_at && ! $b->checked_out_at);

            return view('vendor.bookings.stay-board', compact('business', 'arrivals', 'inHouse', 'departures'));
        })->name('bookings.stay-board');

        Route::put('/bookings/{bookingId}/check-in', function (Request $request, $bookingId) {
            $booking = Booking::findOrFail($bookingId);
            abort_unless(Business::where('id', $booking->business_id)->where('created_by', Auth::id())->exists(), 403);
            $validated = $request->validate(['room_number' => 'nullable|string|max:60']);
            app(BookingWorkflowService::class)->checkIn($booking, $validated['room_number'] ?? null);

            return back()->with('success', 'Guest checked in.');
        })->name('bookings.check-in');

        Route::put('/bookings/{bookingId}/check-out', function (Request $request, $bookingId) {
            $booking = Booking::findOrFail($bookingId);
            abort_unless(Business::where('id', $booking->business_id)->where('created_by', Auth::id())->exists(), 403);
            app(BookingWorkflowService::class)->checkOut($booking);

            return back()->with('success', 'Guest checked out.');
        })->name('bookings.check-out');

        // Transport fleet and requests
        Route::get('/businesses/{businessId}/vehicles', function ($businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('transport'), 404);
            $vehicles = $business->vehicles()->withCount(['trips' => fn ($query) => $query->whereIn('status', ['pending', 'confirmed', 'started'])])->orderBy('sort_order')->get();
            $vehicleTypes = VehicleType::active()->ordered()->get();

            return view('vendor.transport.vehicles', compact('business', 'vehicles', 'vehicleTypes'));
        })->name('vehicles');

        Route::post('/businesses/{businessId}/vehicles', function (Request $request, $businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('transport'), 404);
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'required|exists:vehicle_types,slug',
                'service_mode' => 'required|in:taxi,shared,rental,goods,bus',
                'seats' => 'required|integer|min:1|max:100',
                'seat_layout' => 'nullable|array',
                'seat_layout.*.label' => 'required|string|max:20',
                'seat_layout.*.row' => 'nullable|integer|min:1',
                'seat_layout.*.col' => 'nullable|integer|min:1',
                'seat_layout.*.deck' => 'nullable|string|max:20',
                'seat_layout.*.type' => 'nullable|string|max:20',
                'capacity_value' => 'nullable|numeric|min:0.01|max:100000',
                'capacity_unit' => 'required|in:seats,kg,tons,vehicle',
                'base_fare' => 'required|numeric|min:0',
                'fare_per_km' => 'required|numeric|min:0',
                'price_per_day' => 'nullable|numeric|min:0',
                'min_km' => 'nullable|integer|min:1',
                'registration_number' => 'nullable|string|max:50',
                'terms' => 'nullable|string|max:4000',
                'requires_quote' => 'nullable|boolean',
            ]);
            $validated['business_id'] = $business->id;
            $validated['is_active'] = true;
            $validated['availability_status'] = 'available';
            $validated['requires_quote'] = $request->has('requires_quote');
            $validated['price_per_day'] = $validated['price_per_day'] ?? null;
            if ($request->filled('seat_layout_json')) {
                $decoded = json_decode($request->input('seat_layout_json'), true);
                if (is_array($decoded)) {
                    $validated['seat_layout'] = array_values(array_filter($decoded, fn ($seat) => ! empty($seat['label'])));
                    $validated['seats'] = count($validated['seat_layout']);
                }
            }
            Vehicle::create($validated);

            return back()->with('success', 'Transport option added.');
        })->name('vehicles.store');

        Route::put('/businesses/{businessId}/vehicles/{vehicleId}', function (Request $request, $businessId, $vehicleId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $vehicle = Vehicle::where('business_id', $business->id)->findOrFail($vehicleId);
            $validated = $request->validate([
                'name' => 'required|string|max:255', 'type' => 'nullable|exists:vehicle_types,slug', 'service_mode' => 'required|in:taxi,shared,rental,goods,bus',
                'seats' => 'required|integer|min:1|max:100',
                'seat_layout' => 'nullable|array',
                'seat_layout.*.label' => 'required|string|max:20',
                'seat_layout.*.row' => 'nullable|integer|min:1',
                'seat_layout.*.col' => 'nullable|integer|min:1',
                'seat_layout.*.deck' => 'nullable|string|max:20',
                'seat_layout.*.type' => 'nullable|string|max:20',
                'capacity_value' => 'nullable|numeric|min:0.01|max:100000',
                'capacity_unit' => 'required|in:seats,kg,tons,vehicle', 'base_fare' => 'required|numeric|min:0',
                'fare_per_km' => 'required|numeric|min:0', 'price_per_day' => 'nullable|numeric|min:0',
                'availability_status' => 'required|in:available,busy,offline',
                'next_available_at' => 'nullable|date', 'requires_quote' => 'nullable|boolean', 'is_active' => 'nullable|boolean',
                'terms' => 'nullable|string|max:4000',
            ]);
            $validated['requires_quote'] = $request->has('requires_quote');
            $validated['is_active'] = $request->has('is_active');
            $validated['price_per_day'] = $validated['price_per_day'] ?? null;
            if ($request->filled('seat_layout_json')) {
                $decoded = json_decode($request->input('seat_layout_json'), true);
                if (is_array($decoded)) {
                    $validated['seat_layout'] = array_values(array_filter($decoded, fn ($seat) => ! empty($seat['label'])));
                    $validated['seats'] = count($validated['seat_layout']);
                } else {
                    $validated['seat_layout'] = null;
                }
            }
            $vehicle->update($validated);

            return back()->with('success', 'Transport option updated.');
        })->name('vehicles.update');

        Route::delete('/businesses/{businessId}/vehicles/{vehicleId}', function ($businessId, $vehicleId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $vehicle = Vehicle::where('business_id', $business->id)->findOrFail($vehicleId);
            abort_if($vehicle->trips()->whereIn('status', ['pending', 'confirmed', 'started'])->exists(), 422, 'Cannot delete a vehicle with active requests.');
            abort_if($vehicle->schedules()->where('status', 'scheduled')->exists(), 422, 'Cannot delete a vehicle with active schedules.');
            $vehicle->delete();

            return back()->with('success', 'Transport option deleted.');
        })->name('vehicles.destroy');

        // Vendor transport schedules (route + date + seats)
        Route::get('/businesses/{businessId}/schedules', function ($businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('transport'), 404);
            $schedules = $business->schedules()
                ->with(['vehicle:id,name,type,image', 'route:id,origin,destination'])
                ->withCount(['bookings' => fn ($q) => $q->whereIn('status', ['pending', 'confirmed'])])
                ->orderByDesc('departure_date')
                ->paginate(20);

            return view('vendor.transport.schedules', compact('business', 'schedules'));
        })->name('schedules');

        Route::get('/businesses/{businessId}/schedules/create', function ($businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('transport'), 404);
            $vehicles = $business->vehicles()->where('is_active', true)->orderBy('name')->get();
            $routes = TransportRoute::active()->ordered()->get(['id', 'origin', 'destination']);

            return view('vendor.transport.schedule-form', compact('business', 'vehicles', 'routes'));
        })->name('schedules.create');

        Route::post('/businesses/{businessId}/schedules', function (Request $request, $businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('transport'), 404);
            $validated = $request->validate([
                'vehicle_id' => 'required|exists:vehicles,id',
                'transport_route_id' => 'nullable|exists:transport_routes,id',
                'origin' => 'required|string|max:255',
                'destination' => 'required|string|max:255',
                'distance_km' => 'nullable|numeric|min:0.1|max:10000',
                'estimated_hours' => 'nullable|numeric|min:0.5|max:48',
                'departure_date' => 'required|date|after_or_equal:today',
                'departure_time' => 'required|date_format:H:i',
                'seats_capacity' => 'required|integer|min:1|max:100',
                'price' => 'required|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
                'boarding_stops' => 'nullable|array',
                'boarding_stops.*.name' => 'required|string|max:255',
                'boarding_stops.*.time' => 'nullable|date_format:H:i',
                'boarding_stops.*.price_offset' => 'nullable|numeric|min:0',
                'drop_stops' => 'nullable|array',
                'drop_stops.*.name' => 'required|string|max:255',
                'drop_stops.*.time' => 'nullable|date_format:H:i',
                'drop_stops.*.price_offset' => 'nullable|numeric|min:0',
            ]);

            $vehicle = Vehicle::where('business_id', $business->id)->findOrFail($validated['vehicle_id']);
            $validated['seats_capacity'] = min((int) $validated['seats_capacity'], max((int) $vehicle->seats, 1));
            $validated['business_id'] = $business->id;
            $validated['status'] = 'scheduled';
            if (! empty($validated['transport_route_id'])) {
                $route = TransportRoute::find($validated['transport_route_id']);
                $validated['origin'] = $route->origin;
                $validated['destination'] = $route->destination;
                $validated['distance_km'] = $validated['distance_km'] ?? $route->distance_km;
                $validated['estimated_minutes'] = $validated['estimated_hours'] ?? null
                    ? (int) round((float) $validated['estimated_hours'] * 60)
                    : $route->estimated_minutes;
            } elseif (! empty($validated['estimated_hours'])) {
                $validated['estimated_minutes'] = (int) round((float) $validated['estimated_hours'] * 60);
            }
            unset($validated['estimated_hours']);
            $validated['boarding_stops'] = array_values(array_filter($validated['boarding_stops'] ?? [], fn ($stop) => ! empty($stop['name'])));
            $validated['drop_stops'] = array_values(array_filter($validated['drop_stops'] ?? [], fn ($stop) => ! empty($stop['name'])));
            if (empty($validated['boarding_stops'])) {
                $validated['boarding_stops'] = null;
            }
            if (empty($validated['drop_stops'])) {
                $validated['drop_stops'] = null;
            }
            VehicleSchedule::create($validated);

            return redirect()->route('vendor.schedules', $business->id)->with('success', 'Departure added.');
        })->name('schedules.store');

        Route::delete('/businesses/{businessId}/schedules/{scheduleId}', function ($businessId, $scheduleId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $schedule = VehicleSchedule::where('business_id', $business->id)->findOrFail($scheduleId);
            abort_if($schedule->bookings()->whereIn('status', ['pending', 'confirmed'])->exists(), 422, 'Cannot delete a departure with active bookings.');
            $schedule->delete();

            return back()->with('success', 'Departure deleted.');
        })->name('schedules.destroy');

        Route::get('/businesses/{businessId}/schedules/{scheduleId}/edit', function ($businessId, $scheduleId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('transport'), 404);
            $schedule = VehicleSchedule::where('business_id', $business->id)->findOrFail($scheduleId);
            abort_if($schedule->bookings()->whereIn('status', ['pending', 'confirmed'])->exists(), 422, 'Cannot edit a departure with active bookings.');
            $vehicles = $business->vehicles()->where('is_active', true)->orderBy('name')->get();
            $routes = TransportRoute::active()->ordered()->get(['id', 'origin', 'destination']);

            return view('vendor.transport.schedule-form', compact('business', 'vehicles', 'routes', 'schedule'));
        })->name('schedules.edit');

        Route::put('/businesses/{businessId}/schedules/{scheduleId}', function (Request $request, $businessId, $scheduleId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('transport'), 404);
            $schedule = VehicleSchedule::where('business_id', $business->id)->findOrFail($scheduleId);
            abort_if($schedule->bookings()->whereIn('status', ['pending', 'confirmed'])->exists(), 422, 'Cannot edit a departure with active bookings.');

            $validated = $request->validate([
                'vehicle_id' => 'required|exists:vehicles,id',
                'transport_route_id' => 'nullable|exists:transport_routes,id',
                'origin' => 'required|string|max:255',
                'destination' => 'required|string|max:255',
                'distance_km' => 'nullable|numeric|min:0.1|max:10000',
                'estimated_hours' => 'nullable|numeric|min:0.5|max:48',
                'departure_date' => 'required|date',
                'departure_time' => 'required|date_format:H:i',
                'seats_capacity' => 'required|integer|min:1|max:100',
                'price' => 'required|numeric|min:0',
                'notes' => 'nullable|string|max:1000',
                'boarding_stops' => 'nullable|array',
                'boarding_stops.*.name' => 'required|string|max:255',
                'boarding_stops.*.time' => 'nullable|date_format:H:i',
                'boarding_stops.*.price_offset' => 'nullable|numeric|min:0',
                'drop_stops' => 'nullable|array',
                'drop_stops.*.name' => 'required|string|max:255',
                'drop_stops.*.time' => 'nullable|date_format:H:i',
                'drop_stops.*.price_offset' => 'nullable|numeric|min:0',
            ]);

            $vehicle = Vehicle::where('business_id', $business->id)->findOrFail($validated['vehicle_id']);
            $validated['seats_capacity'] = min((int) $validated['seats_capacity'], max((int) $vehicle->seats, 1));
            if (! empty($validated['transport_route_id'])) {
                $route = TransportRoute::find($validated['transport_route_id']);
                $validated['origin'] = $route->origin;
                $validated['destination'] = $route->destination;
                $validated['distance_km'] = $validated['distance_km'] ?? $route->distance_km;
                $validated['estimated_minutes'] = $validated['estimated_hours'] ?? null
                    ? (int) round((float) $validated['estimated_hours'] * 60)
                    : $route->estimated_minutes;
            } elseif (! empty($validated['estimated_hours'])) {
                $validated['estimated_minutes'] = (int) round((float) $validated['estimated_hours'] * 60);
            }
            unset($validated['estimated_hours']);
            $validated['boarding_stops'] = array_values(array_filter($validated['boarding_stops'] ?? [], fn ($stop) => ! empty($stop['name'])));
            $validated['drop_stops'] = array_values(array_filter($validated['drop_stops'] ?? [], fn ($stop) => ! empty($stop['name'])));
            if (empty($validated['boarding_stops'])) {
                $validated['boarding_stops'] = null;
            }
            if (empty($validated['drop_stops'])) {
                $validated['drop_stops'] = null;
            }
            $schedule->update($validated);

            return redirect()->route('vendor.schedules', $business->id)->with('success', 'Departure updated.');
        })->name('schedules.update');

        // Vendor incoming seat bookings
        Route::get('/businesses/{businessId}/schedule-bookings', function ($businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('transport'), 404);
            $query = $business->scheduleBookings()
                ->with(['schedule.vehicle:id,name,type,image', 'schedule:id,origin,destination,departure_date,departure_time'])
                ->latest();

            if ($status = request('status')) {
                $query->where('status', $status);
            }
            if ($date = request('date')) {
                $query->whereHas('schedule', fn ($q) => $q->whereDate('departure_date', $date));
            }

            $bookings = $query->paginate(20)->withQueryString();

            return view('vendor.transport.schedule-bookings', compact('business', 'bookings'));
        })->name('schedule-bookings');

        Route::put('/schedule-bookings/{id}/status', function (Request $request, $id) {
            $booking = ScheduleBooking::with('business')->findOrFail($id);
            abort_unless($booking->business->created_by === Auth::id(), 403);
            $validated = $request->validate([
                'status' => 'required|in:confirmed,cancelled,completed,no_show',
                'cancellation_reason' => 'nullable|string|max:500',
            ]);
            match ($validated['status']) {
                'confirmed' => (function () use ($booking) {
                    // A pending seat request older than 30 minutes has had its
                    // seats released for re-sale — confirming it would double-sell.
                    if ($booking->status === 'pending' && $booking->created_at && $booking->created_at->lt(now()->subMinutes(30))) {
                        abort(422, 'This seat request expired — its seats were released. Ask the customer to book again.');
                    }
                    $booking->markConfirmed();
                })(),
                'completed' => $booking->markCompleted(),
                'cancelled' => $booking->markCancelled($validated['cancellation_reason'] ?? null),
                default => $booking->markNoShow(),
            };

            return back()->with('success', 'Seat booking '.$validated['status'].'.');
        })->name('schedule-bookings.status');

        // Vendor incoming vehicle hire / rental bookings
        Route::get('/businesses/{businessId}/rentals', function ($businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('transport'), 404);
            $query = $business->vehicleRentals()->with('vehicle:id,name,type,image')->latest();

            if ($status = request('status')) {
                $query->where('status', $status);
            }

            $rentals = $query->paginate(20)->withQueryString();

            return view('vendor.transport.rentals', compact('business', 'rentals'));
        })->name('rentals');

        Route::put('/rentals/{id}/status', function (Request $request, $id) {
            $rental = VehicleRental::with('business')->findOrFail($id);
            abort_unless($rental->business->created_by === Auth::id(), 403);
            $validated = $request->validate([
                'status' => 'required|in:confirmed,cancelled,completed',
                'cancellation_reason' => 'nullable|string|max:500',
            ]);
            match ($validated['status']) {
                'confirmed' => $rental->markConfirmed(),
                'completed' => $rental->markCompleted(),
                'cancelled' => $rental->markCancelled($validated['cancellation_reason'] ?? null),
            };

            return back()->with('success', 'Vehicle hire '.$validated['status'].'.');
        })->name('rentals.status');

        Route::put('/rentals/{id}/payment-status', function (Request $request, $id) {
            $rental = VehicleRental::with('business')->findOrFail($id);
            abort_unless($rental->business->created_by === Auth::id(), 403);
            $request->validate(['payment_status' => 'required|in:paid']);
            $rental->update(['payment_status' => 'paid']);

            return back()->with('success', 'Hire payment marked as collected.');
        })->name('rentals.payment-status');

        Route::get('/businesses/{businessId}/trips', function ($businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('transport'), 404);
            $query = $business->trips()->with('vehicle')->latest();
            if ($status = request('status')) {
                $query->where('status', $status);
            }
            if ($search = request('search')) {
                $safe = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(fn ($q) => $q->where('customer_name', 'like', $safe)->orWhere('customer_phone', 'like', $safe)->orWhere('pickup_location', 'like', $safe));
            }

            return view('vendor.transport.trips', ['business' => $business, 'trips' => $query->paginate(20)->withQueryString()]);
        })->name('trips');

        Route::put('/trips/{tripId}/status', function (Request $request, $tripId) {
            $trip = Trip::with('business')->findOrFail($tripId);
            abort_unless($trip->business->created_by === Auth::id(), 403);
            $validated = $request->validate(['status' => 'required|in:confirmed,started,completed,cancelled', 'cancellation_reason' => 'nullable|string|max:500', 'driver_name' => 'nullable|string|max:255', 'driver_phone' => 'nullable|string|max:20']);
            app(TripWorkflowService::class)->transition($trip, $validated['status'], $validated['cancellation_reason'] ?? null, ['name' => $validated['driver_name'] ?? null, 'phone' => $validated['driver_phone'] ?? null]);

            return back()->with('success', 'Transport request '.$validated['status'].'.');
        })->name('trips.status');

        Route::put('/trips/{tripId}/quote', function (Request $request, $tripId) {
            $trip = Trip::with('business')->findOrFail($tripId);
            abort_unless($trip->business->created_by === Auth::id(), 403);
            $validated = $request->validate(['fare' => 'required|numeric|min:0|max:10000000', 'quote_notes' => 'nullable|string|max:1000']);
            app(TripWorkflowService::class)->quote($trip, (float) $validated['fare'], $validated['quote_notes'] ?? null);

            return back()->with('success', 'Fare quote saved. Contact the customer to confirm it.');
        })->name('trips.quote');

        Route::put('/trips/{tripId}/payment-status', function (Request $request, $tripId) {
            $trip = Trip::with('business')->findOrFail($tripId);
            abort_unless($trip->business->created_by === Auth::id(), 403);
            $request->validate(['payment_status' => 'required|in:paid']);
            app(TripWorkflowService::class)->markCashCollected($trip);

            return back()->with('success', 'Cash payment marked as collected.');
        })->name('trips.payment-status');

        // Orders
        Route::get('/businesses/{businessId}/orders', function ($businessId) {
            $user = Auth::user();
            $business = Business::where('created_by', $user->id)->findOrFail($businessId);
            abort_unless($business->hasModule('orders'), 404);
            $query = Order::where('business_id', $business->id)->with('items')->latest();

            if ($status = request('status')) {
                $query->where('status', $status);
            }
            if ($paymentStatus = request('payment_status')) {
                $query->where('payment_status', $paymentStatus);
            }
            if ($search = request('search')) {
                $safe = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(function ($searchQuery) use ($safe) {
                    $searchQuery->where('order_number', 'like', $safe)
                        ->orWhere('customer_name', 'like', $safe)
                        ->orWhere('customer_phone', 'like', $safe);
                });
            }

            $orders = $query->paginate(20)->withQueryString();

            return view('vendor.orders.index', compact('orders', 'business'));
        })->name('orders');

        Route::put('/orders/{id}/status', function (Request $request, $id) {
            $order = Order::with('business')->findOrFail($id);
            $user = Auth::user();
            abort_unless($order->business->hasModule('orders'), 404);
            if ($order->business->created_by !== $user->id) {
                abort(403);
            }

            $validated = $request->validate([
                'status' => 'required|in:confirmed,preparing,ready,out_for_delivery,delivered,cancelled,rejected',
                'cancellation_reason' => 'nullable|string',
            ]);
            app(OrderWorkflowService::class)->transition(
                $order,
                $validated['status'],
                $validated['cancellation_reason'] ?? null,
            );

            return back()->with('success', 'Order '.$validated['status'].'.');
        })->name('orders.status');

        Route::put('/orders/{id}/payment-status', function (Request $request, $id) {
            $order = Order::with('business')->findOrFail($id);
            abort_unless($order->business->created_by === Auth::id(), 403);
            $request->validate(['payment_status' => 'required|in:paid']);

            app(OrderWorkflowService::class)->markCashCollected($order);

            return back()->with('success', 'Cash payment marked as collected.');
        })->name('orders.payment-status');

        // Experience management
        Route::get('/businesses/{businessId}/experiences', function ($businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $experienceService = app(BusinessExperienceService::class);
            $readiness = $experienceService->calculateReadiness($business);

            return view('vendor.businesses.experiences', [
                'business' => $business,
                'readiness' => $readiness,
                'globallyEnabledExperiences' => app(LaunchControlService::class)->enabledExperienceKeys(),
            ]);
        })->name('businesses.experiences');

        Route::put('/businesses/{businessId}/experiences', function (Request $request, $businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $validated = $request->validate([
                'primary_experience' => 'nullable|string|in:directory,retail,restaurant,appointment,stay,turf,taxi,shared_transport,vehicle_rental,goods_transport,seat_event',
                'enabled_experiences' => 'nullable|array',
                'enabled_experiences.*' => 'string|in:directory,retail,restaurant,appointment,stay,turf,taxi,shared_transport,vehicle_rental,goods_transport,seat_event',
            ]);

            $business->update([
                'primary_experience' => $validated['primary_experience'] ?? $business->primary_experience,
                'enabled_experiences' => app(LaunchControlService::class)->filterExperiences($validated['enabled_experiences'] ?? $business->enabled_experiences),
            ]);

            return back()->with('success', 'Experiences updated.');
        })->name('businesses.experiences.update');

        Route::put('/businesses/{businessId}/experiences/{experience}/availability', function (Request $request, $businessId, $experience) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $request->validate([
                'availability_mode' => 'required|in:live,request,contact',
            ]);

            $config = $business->experience_config ?? [];
            $config[$experience]['availability_mode'] = $request->availability_mode;
            $business->update([
                'experience_config' => $config,
                'availability_updated_at' => now(),
                'availability_is_stale' => false,
            ]);

            return back()->with('success', ucfirst(str_replace('_', ' ', $experience)).' availability set to '.ucfirst($request->availability_mode).'.');
        })->name('businesses.experiences.availability');

        // Turf inventory management
        Route::get('/businesses/{businessId}/turf', function ($businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('turf') || $business->hasModule('bookings'), 404);
            $services = $business->services()->where('booking_mode', 'slot')->with(['timeSlots' => function ($q) {
                $q->orderBy('day_of_week')->orderBy('start_time');
            }])->get();

            return view('vendor.inventory.turf', compact('business', 'services'));
        })->name('businesses.turf');

        Route::put('/businesses/{businessId}/turf/services/{serviceId}/toggle', function (Request $request, $businessId, $serviceId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $service->update(['is_active' => $request->boolean('is_active')]);

            return back()->with('success', 'Turf availability updated.');
        })->name('businesses.turf.toggle');

        // Room/stay inventory management
        Route::get('/businesses/{businessId}/rooms', function ($businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);
            $services = $business->services()->where('booking_mode', 'stay')->get();

            return view('vendor.inventory.rooms', compact('business', 'services'));
        })->name('businesses.rooms');

        Route::put('/businesses/{businessId}/rooms/{serviceId}', function (Request $request, $businessId, $serviceId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $validated = $request->validate([
                'inventory_units' => 'required|integer|min:0|max:10000',
                'price' => 'required|numeric|min:0',
                'is_active' => 'nullable|boolean',
            ]);
            $validated['is_active'] = $request->boolean('is_active');
            $service->update($validated);

            return back()->with('success', 'Room updated.');
        })->name('businesses.rooms.update');

        // Appointment calendar management
        Route::get('/businesses/{businessId}/appointments', function ($businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);
            $services = $business->services()->where('booking_mode', 'appointment')->with(['timeSlots' => function ($q) {
                $q->orderBy('day_of_week')->orderBy('start_time');
            }])->get();

            return view('vendor.inventory.appointments', compact('business', 'services'));
        })->name('businesses.appointments');

        Route::put('/businesses/{businessId}/appointments/{serviceId}', function (Request $request, $businessId, $serviceId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $validated = $request->validate([
                'is_active' => 'nullable|boolean',
                'duration' => 'nullable|integer|min:15|max:1440',
                'capacity' => 'nullable|integer|min:1',
            ]);
            $validated['is_active'] = $request->boolean('is_active');
            $service->update($validated);

            return back()->with('success', 'Appointment service updated.');
        })->name('businesses.appointments.update');

        Route::post('/businesses/{businessId}/appointments/{serviceId}/slots', function (Request $request, $businessId, $serviceId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $validated = $request->validate([
                'day_of_week' => 'nullable|integer|between:0,6',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'capacity' => 'required|integer|min:1|max:10000',
                'price_override' => 'nullable|numeric|min:0',
            ]);
            $validated['service_id'] = $service->id;
            $validated['is_active'] = true;
            TimeSlot::create($validated);

            return back()->with('success', 'Time slot added.');
        })->name('businesses.appointments.slots.store');

        Route::delete('/businesses/{businessId}/appointments/{serviceId}/slots/{slotId}', function ($businessId, $serviceId, $slotId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $slot = TimeSlot::where('service_id', $service->id)->findOrFail($slotId);
            abort_if($slot->bookings()->whereIn('status', ['pending', 'confirmed'])->exists(), 422, 'Cannot delete a slot with active bookings.');
            $slot->delete();

            return back()->with('success', 'Time slot deleted.');
        })->name('businesses.appointments.slots.destroy');

        // Seat event inventory
        Route::get('/businesses/{businessId}/seats', function ($businessId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            abort_unless($business->hasModule('bookings'), 404);
            $services = $business->services()->where('booking_mode', 'seat')->get();

            return view('vendor.inventory.seats', compact('business', 'services'));
        })->name('businesses.seats');

        Route::put('/businesses/{businessId}/seats/{serviceId}', function (Request $request, $businessId, $serviceId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $service = Service::where('business_id', $business->id)->findOrFail($serviceId);
            $validated = $request->validate([
                'capacity' => 'required|integer|min:0',
                'price' => 'required|numeric|min:0',
                'is_active' => 'nullable|boolean',
            ]);
            $validated['is_active'] = $request->boolean('is_active');
            $service->update($validated);

            return back()->with('success', 'Seat event updated.');
        })->name('businesses.seats.update');

        // Vehicle availability toggle
        Route::put('/businesses/{businessId}/vehicles/{vehicleId}/availability', function (Request $request, $businessId, $vehicleId) {
            $business = Business::where('created_by', Auth::id())->findOrFail($businessId);
            $vehicle = Vehicle::where('business_id', $business->id)->findOrFail($vehicleId);
            $validated = $request->validate([
                'availability_status' => 'required|in:available,busy,offline',
            ]);
            $vehicle->update($validated);

            return back()->with('success', 'Vehicle availability updated.');
        })->name('businesses.vehicles.availability');

        // Settings
        Route::get('/settings', function () {
            $user = Auth::user();

            return view('vendor.settings.index', compact('user'));
        })->name('settings');

        Route::put('/settings', function (Request $request) {
            $user = Auth::user();
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
            ]);
            $user->update($validated);

            return redirect()->route('vendor.settings')->with('success', 'Profile updated.');
        })->name('settings.update');

        Route::put('/settings/password', function (Request $request) {
            $validated = $request->validate([
                'current_password' => 'required|current_password',
                'password' => 'required|string|min:6|confirmed',
            ]);
            Auth::user()->update(['password' => bcrypt($validated['password'])]);

            return redirect()->route('vendor.settings')->with('success', 'Password changed.');
        })->name('settings.password');

        // Vendor Analytics
        Route::get('/analytics', [AnalyticsController::class, 'overview'])->name('analytics');
        Route::get('/analytics/popular-products', [AnalyticsController::class, 'popularProducts'])->name('analytics.popular-products');
        Route::get('/analytics/revenue-chart', [AnalyticsController::class, 'revenueChart'])->name('analytics.revenue-chart');

        // Vendor Media Library
        Route::get('/media', [MediaController::class, 'index'])->name('media');
        Route::post('/media/upload', [MediaController::class, 'upload'])->name('media.upload');
        Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

        // Notifications
        Route::get('/notifications', [VendorNotificationController::class, 'index'])->name('notifications');
        Route::get('/notifications/{notification}', [VendorNotificationController::class, 'show'])->name('notifications.show');
        Route::post('/notifications/{notification}/read', [VendorNotificationController::class, 'markRead'])->name('notifications.read');
        Route::post('/notifications/read-all', [VendorNotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::delete('/notifications/{notification}', [VendorNotificationController::class, 'destroy'])->name('notifications.destroy');
    });
});

// ─── Health ───
Route::get('/health/backups', [HealthController::class, 'backupStatus'])->middleware(['auth', 'admin'])->name('health.backups');
