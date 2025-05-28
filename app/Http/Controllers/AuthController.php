<?php

namespace App\Http\Controllers;

use App\Models\PhoneOtp;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AuthController extends Controller
{
    protected $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $fields = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20|unique:users',
            'role' => 'sometimes|in:admin,shop_owner,staff,user',
        ]);

        // Default role to 'user' if not provided
        if (!isset($fields['role'])) {
            $fields['role'] = 'user';
        }

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => Hash::make($fields['password']),
            'phone' => $fields['phone'] ?? null,
            'role' => $fields['role'],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }

    /**
     * Login with email and password
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $deviceName = $request->device_name ?? ($request->userAgent() ?? 'unknown');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    public function getAllUsers()
    {
        if (!Auth::user() || Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = User::all();

        return response()->json($users);
    }


    /**
     * Send OTP for phone authentication
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'name' => 'required|string|max:255',
        ]);
    
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
        PhoneOtp::updateOrCreate(
            ['phone' => $request->phone],
            [
                'otp' => $otp,
                'expires_at' => now()->addMinutes(10),
                'name' => $request->name, // Store the name
            ]
        );
    
        $smsSent = $this->twilioService->sendOTP($request->phone, $otp);
    
        if (!$smsSent) {
            return response()->json([
                'message' => 'Failed to send OTP. Please try again later.',
            ], 500);
        }
    
        return response()->json([
            'message' => 'OTP sent successfully to your phone',
            'otp' => $otp // For testing only
        ]);
    }

    /**
     * Verify OTP and login or register user
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:20',
            'otp' => 'required|string|max:6',
        ]);
    
        $phoneOtp = PhoneOtp::where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->first();
    
        if (!$phoneOtp || !$phoneOtp->isValid()) {
            throw ValidationException::withMessages([
                'otp' => ['The provided OTP is invalid or expired.'],
            ]);
        }
    
        $user = User::where('phone', $request->phone)->first();
    
        if (!$user) {
            if (!$phoneOtp->name) {
                return response()->json([
                    'message' => 'New user requires name',
                    'requires_registration' => true
                ], 422);
            }
    
            $uniqueId = uniqid();
            $email = 'phone_user_' . preg_replace('/[^0-9]/', '', $request->phone) . '_' . $uniqueId . '@example.com';
    
            $user = User::create([
                'name' => $phoneOtp->name, // Use stored name
                'phone' => $request->phone,
                'phone_verified_at' => now(),
                'role' => 'user',
                'email' => $email,
                'password' => Hash::make(Str::random(16)),
            ]);
        } else {
            $user->phone_verified_at = now();
            $user->save();
        }
    
        $token = $user->createToken('auth_token')->plainTextToken;
    
        return response()->json([
            'message' => 'OTP verified successfully',
            'token' => $token,
            'user' => $user
        ]);
    }
    
    
    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google callback
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            $user = User::where('email', $googleUser->email)->first();
            
            if (!$user) {
                $user = User::create([
                    'name' => $googleUser->name,
                    'email' => $googleUser->email,
                    'google_id' => $googleUser->id,
                    'role' => 'user',
                    'password' => Hash::make(Str::random(16)),
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->google_id = $googleUser->id;
                $user->email_verified_at = $user->email_verified_at ?? now();
                $user->save();
            }
            
            $token = $user->createToken('google_auth')->plainTextToken;
            
            // For SPA integration, redirect with token
            return redirect()->away(env('SPA_URL', 'http://localhost:3000') . '/auth/callback?token=' . $token);
            
        } catch (\Exception $e) {
            return redirect()->away(env('SPA_URL', 'http://localhost:3000') . '/auth/callback?error=' . $e->getMessage());
        }
    }

    /**
     * Logout and revoke token
     */
    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Handle invitation authentication
     */
    public function handleInvitation(string $token)
    {
        $invitation = UserInvitation::where('token', $token)
            ->with('user')
            ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invalid invitation link.'
            ], 404);
        }

        if (!$invitation->isValid()) {
            return response()->json([
                'message' => 'This invitation has expired or already been used.'
            ], 400);
        }

        // Get the user
        $user = $invitation->user;

        // Mark invitation as used
        $invitation->update([
            'used_at' => now()
        ]);

        // Create authentication token
        $authToken = $user->createToken('invitation_auth')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $authToken
        ]);
    }
}