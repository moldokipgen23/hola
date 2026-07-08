<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        $user = User::updateOrCreate(
            ['google_id' => $payload['sub']],
            [
                'name' => $payload['name'] ?? $payload['email'],
                'email' => $payload['email'],
                'avatar' => $payload['picture'] ?? null,
                'email_verified_at' => now(),
            ]
        );

        return response()->json([
            'token' => $user->createToken('hola')->plainTextToken,
            'user' => $user,
        ]);
    }

    // ─── OTP ───
    public function sendOtp(Request $request)
    {
        $request->validate(['phone' => 'required|string']);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user = User::firstOrCreate(
            ['phone' => $request->phone],
            ['name' => 'User', 'password' => Hash::make(Str::random(16))]
        );

        $user->update(['otp' => $otp, 'otp_expires_at' => now()->addMinutes(10)]);

        // TODO: Integrate actual SMS provider
        \Log::info("OTP for {$request->phone}: {$otp}");

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

        return response()->json([
            'token' => $user->createToken('hola')->plainTextToken,
            'user' => $user,
            'message' => 'Account created. Please verify your email.',
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

        return response()->json([
            'token' => $user->createToken('hola-admin')->plainTextToken,
            'user' => $user,
        ]);
    }

    public function logout(Request $request)
    {
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
        $request->user()->markEmailAsVerified();

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
