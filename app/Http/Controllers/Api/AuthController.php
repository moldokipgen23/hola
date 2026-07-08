<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function adminLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($user->role !== 'admin') {
            throw ValidationException::withMessages([
                'email' => ['Unauthorized. Admin access only.'],
            ]);
        }

        $token = $user->createToken('hola-admin')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function googleLogin(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $response = Http::get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'access_token' => $request->token,
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'token' => ['Invalid Google token.'],
            ]);
        }

        $googleUser = $response->json();

        $user = User::updateOrCreate(
            ['google_id' => $googleUser['sub']],
            [
                'name' => $googleUser['name'],
                'email' => $googleUser['email'],
                'avatar' => $googleUser['picture'] ?? null,
                'email_verified_at' => now(),
            ]
        );

        $token = $user->createToken('hola-app')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:15',
        ]);

        $otp = (string) random_int(100000, 999999);

        $user = User::updateOrCreate(
            ['phone' => $request->phone],
            [
                'name' => 'User ' . substr($request->phone, -4),
                'password' => Hash::make($otp),
            ]
        );

        $user->otp = $otp;
        $user->otp_expires_at = now()->addMinutes(10);
        $user->save();

        return response()->json([
            'message' => 'OTP sent successfully.',
            'phone' => $request->phone,
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:15',
            'otp' => 'required|string|size:6',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'phone' => ['User not found.'],
            ]);
        }

        if ($user->otp !== $request->otp) {
            throw ValidationException::withMessages([
                'otp' => ['Invalid OTP.'],
            ]);
        }

        if ($user->otp_expires_at && $user->otp_expires_at->isPast()) {
            throw ValidationException::withMessages([
                'otp' => ['OTP has expired.'],
            ]);
        }

        $user->phone_verified_at = now();
        $user->otp = null;
        $user->otp_expires_at = null;
        $user->save();

        $token = $user->createToken('hola-app')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('hola-app')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $token = $user->createToken('hola-app')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'avatar' => 'sometimes|string|max:255',
        ]);

        $user = $request->user();
        $user->update($request->only(['name', 'avatar']));

        return response()->json([
            'user' => $user,
        ]);
    }
}
