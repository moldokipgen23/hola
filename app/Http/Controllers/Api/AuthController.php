<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // ─── Google OAuth ───
    public function googleLogin(Request $request)
    {
        $request->validate([
            'google_token' => 'required|string',
        ]);

        $response = Http::get('https://www.googleapis.com/oauth2/v3/tokeninfo', [
            'id_token' => $request->google_token,
        ]);

        if (!$response->successful() || !$response->json('sub')) {
            return response()->json(['message' => 'Invalid Google token'], 401);
        }

        $payload = $response->json();

        $expectedAud = config('services.google.client_id');
        if (!$expectedAud) {
            return response()->json(['message' => 'Google authentication not configured.'], 503);
        }
        if (($payload['aud'] ?? null) !== $expectedAud) {
            return response()->json(['message' => 'Invalid Google token'], 401);
        }

        if (!empty($payload['exp']) && (int) $payload['exp'] < time()) {
            return response()->json(['message' => 'Expired Google token'], 401);
        }

        $user = User::updateOrCreate(
            ['google_id' => $payload['sub']],
            [
                'name' => $payload['name'] ?? $payload['email'],
                'email' => $payload['email'],
                'avatar' => $payload['picture'] ?? null,
                'email_verified_at' => now(),
            ]
        );

        $user->recordLogin();
        ActivityLogService::log('login', $user);

        return response()->json([
            'token' => $user->createToken('hola')->plainTextToken,
            'user' => $user,
        ]);
    }

    // ─── OTP ───
    public function sendOtp(Request $request)
    {
        $request->validate(['phone' => 'required|string']);

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json(['message' => 'No account found with this phone number. Please register first.'], 404);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update(['otp' => $otp, 'otp_expires_at' => now()->addMinutes(10)]);

        // TODO: Integrate actual SMS provider. Do not log the OTP in plaintext.
        \Log::info("OTP requested for {$request->phone}");

        return response()->json(['message' => 'OTP sent successfully.']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user || $user->otp !== $request->otp || now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'Invalid or expired OTP'], 401);
        }

        $user->update(['otp' => null, 'otp_expires_at' => null, 'phone_verified_at' => now()]);

        $user->recordLogin();
        ActivityLogService::log('login', $user);

        return response()->json([
            'token' => $user->createToken('hola')->plainTextToken,
            'user' => $user,
        ]);
    }

    // ─── Email/Password Auth ───
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        $user->sendEmailVerificationNotification();
        ActivityLogService::log('user_registered', $user);

        return response()->json([
            'token' => $user->createToken('hola')->plainTextToken,
            'user' => $user,
            'message' => 'Account created. Please verify your email.',
        ]);
    }

    public function registerOwner(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'business_name' => 'required|string|max:255',
            'business_address' => 'nullable|string|max:255',
            'business_category_id' => 'required|exists:categories,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => 'owner',
        ]);

        $slug = \Illuminate\Support\Str::slug($request->business_name);
        if (\App\Models\Business::withTrashed()->where('slug', $slug)->exists()) {
            $slug .= '-' . \Illuminate\Support\Str::random(5);
        }

        $business = \App\Models\Business::create([
            'name' => $request->business_name,
            'slug' => $slug,
            'address' => $request->business_address ?? '',
            'category_id' => $request->business_category_id,
            'created_by' => $user->id,
            'claim_status' => 'claimed',
            'is_active' => true,
        ]);

        $user->sendEmailVerificationNotification();
        ActivityLogService::log('owner_registered', $user, ['business_name' => $request->business_name, 'business_id' => $business->id]);

        return response()->json([
            'token' => $user->createToken('hola')->plainTextToken,
            'user' => $user,
            'business' => $business,
            'message' => 'Owner account and business created successfully.',
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ($user->banned_at) {
            return response()->json(['message' => 'Your account has been banned.' . ($user->ban_reason ? " Reason: {$user->ban_reason}" : '')], 403);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Your account is inactive. Please contact support.'], 403);
        }

        $user->recordLogin();
        ActivityLogService::log('login', $user);

        return response()->json([
            'token' => $user->createToken('hola')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function adminLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !$user->isAdmin() || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ($user->banned_at) {
            return response()->json(['message' => 'Your account has been banned.'], 403);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Your account is inactive.'], 403);
        }

        $user->recordLogin();
        ActivityLogService::log('admin_login', $user);

        return response()->json([
            'token' => $user->createToken('hola-admin')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
        ActivityLogService::log('logout', $request->user());
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    // ─── Profile ───
    public function profile(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('reviews', 'claimRequests'),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'string|max:255',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|string',
        ]);

        $request->user()->update($request->only('name', 'phone', 'avatar'));

        return response()->json([
            'user' => $request->user(),
            'message' => 'Profile updated.',
        ]);
    }

    // ─── Email Verification ───
    public function sendVerificationEmail(Request $request)
    {
        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    public function verifyEmail(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        // Require the signed verification parameters that were emailed to the
        // user. This prevents a logged-in user from self-verifying without
        // actually clicking the verification link sent to their email.
        $request->validate([
            'id' => 'required|integer',
            'hash' => 'required|string',
        ]);

        if ((int) $request->id !== $user->getKey()
            || ! hash_equals((string) $request->hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid verification link.'], 403);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email verified.']);
    }

    // ─── Password Reset ───
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 400);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));
                $user->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 400);
    }

    // ─── Change Password ───
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $request->user()->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $request->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['message' => 'Password changed successfully.']);
    }

    // ─── Delete Account ───
    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required',
        ]);

        if (!Hash::check($request->password, $request->user()->password)) {
            return response()->json(['message' => 'Password is incorrect.'], 422);
        }

        $user = $request->user();

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete user
        $user->delete();

        return response()->json(['message' => 'Account deleted successfully.']);
    }
}
