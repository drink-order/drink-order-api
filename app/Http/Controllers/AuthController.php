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
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    /**
     * Login with email/phone and password
     */
    public function login(Request $request)
    {
        // Add debug logging
        Log::info('=== LOGIN DEBUG ===');
        Log::info('Request method: ' . $request->method());
        Log::info('Request URL: ' . $request->url());
        Log::info('All request data: ', $request->all());
        Log::info('===================');

        // Handle both old format (email field) and new format (email/phone fields)
        $request->validate([
            'email' => 'nullable|string',
            'phone' => 'nullable|string',
            'identifier' => 'nullable|string', // For backward compatibility
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $user = null;
        $loginField = null; // Track which field was used for better error messages

        // Priority: specific email/phone fields, then identifier field
        if ($request->email) {
            // Email login
            Log::info('Login attempt with email: ' . $request->email);
            $user = User::where('email', $request->email)->first();
            $loginField = 'email';
        } elseif ($request->phone) {
            // Phone login - normalize the phone number
            $originalPhone = $request->phone;
            $normalizedPhone = $this->normalizePhoneNumber($request->phone);
            Log::info('Login attempt - Original phone: ' . $originalPhone);
            Log::info('Login attempt - Normalized phone: ' . $normalizedPhone);
            
            $user = User::where('phone', $normalizedPhone)->first();
            Log::info('User found with phone: ' . ($user ? 'Yes (ID: ' . $user->id . ')' : 'No'));
            $loginField = 'phone';
        } elseif ($request->identifier) {
            // Legacy support - check if identifier is email or phone
            $identifier = $request->identifier;
            Log::info('Login attempt with identifier: ' . $identifier);
            
            if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                Log::info('Identifier detected as email');
                $user = User::where('email', $identifier)->first();
                $loginField = 'email';
            } else {
                Log::info('Identifier detected as phone');
                $normalizedPhone = $this->normalizePhoneNumber($identifier);
                Log::info('Normalized phone: ' . $normalizedPhone);
                $user = User::where('phone', $normalizedPhone)->first();
                Log::info('User found with phone: ' . ($user ? 'Yes (ID: ' . $user->id . ')' : 'No'));
                $loginField = 'phone';
            }
        } else {
            throw ValidationException::withMessages([
                'identifier' => ['Email, phone, or identifier is required.'],
            ]);
        }

        if (!$user) {
            Log::info('User not found');
            // Return appropriate error field based on login method
            $errorField = $loginField === 'email' ? 'identifier' : 'identifier';
            throw ValidationException::withMessages([
                $errorField => ['No account found with these credentials.'],
            ]);
        }

        if (!Hash::check($request->password, $user->password)) {
            Log::info('Password check failed for user ID: ' . $user->id);
            // Return appropriate error field based on login method
            $errorField = $loginField === 'email' ? 'identifier' : 'identifier';
            throw ValidationException::withMessages([
                $errorField => ['The provided credentials are incorrect.'],
            ]);
        }

        Log::info('Login successful for user ID: ' . $user->id);

        $deviceName = $request->device_name ?? ($request->userAgent() ?? 'unknown');
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    /**
     * Normalize Cambodian phone number to international format
     */
    private function normalizePhoneNumber($phone)
    {
        if (empty($phone)) {
            return $phone;
        }

        // Remove any spaces, dashes, parentheses, or other non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phone);
        
        Log::info('Phone normalization - Input: ' . $phone . ', Cleaned: ' . $cleaned);
        
        // If starts with +855, keep as is
        if (str_starts_with($cleaned, '+855')) {
            Log::info('Phone already in +855 format');
            return $cleaned;
        }
        
        // If starts with 855, add +
        if (str_starts_with($cleaned, '855')) {
            $result = '+' . $cleaned;
            Log::info('Added + to 855 format: ' . $result);
            return $result;
        }
        
        // If starts with 0 (Cambodian local format), replace with +855
        if (str_starts_with($cleaned, '0')) {
            $result = '+855' . substr($cleaned, 1);
            Log::info('Converted 0 format to +855: ' . $result);
            return $result;
        }
        
        // If just the number without country code (8-9 digits), add +855
        if (strlen($cleaned) >= 8 && strlen($cleaned) <= 9 && !str_contains($cleaned, '+')) {
            $result = '+855' . $cleaned;
            Log::info('Added +855 to bare number: ' . $result);
            return $result;
        }
        
        Log::info('No normalization applied, returning: ' . $cleaned);
        // Return as is if none of the above patterns match
        return $cleaned;
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
            'phone' => 'nullable|string|max:20',
            'role' => 'sometimes|in:admin,shop_owner,staff,user',
        ]);

        // Default role to 'user' if not provided
        if (!isset($fields['role'])) {
            $fields['role'] = 'user';
        }

        // Normalize phone number if provided
        $normalizedPhone = null;
        if (!empty($fields['phone'])) {
            $normalizedPhone = $this->normalizePhoneNumber($fields['phone']);
            
            // Check for unique phone number
            $existingPhone = User::where('phone', $normalizedPhone)->first();
            if ($existingPhone) {
                throw ValidationException::withMessages([
                    'phone' => ['The phone number has already been taken.'],
                ]);
            }
        }

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => Hash::make($fields['password']),
            'phone' => $normalizedPhone,
            'role' => $fields['role'],
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }

    public function getAllUsers()
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        
        if ($user->role === 'admin') {
            $users = User::all();
        } elseif ($user->role === 'shop_owner') {
            $users = User::where('role', 'staff')->get();
        } else {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        
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
            'password' => 'nullable|string|min:8', // Add password validation
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

            // Check if password is provided for new user registration
            if (!$request->password) {
                return response()->json([
                    'message' => 'Password is required for new user registration',
                    'requires_password' => true
                ], 422);
            }

            $uniqueId = uniqid();
            $email = 'phone_user_' . preg_replace('/[^0-9]/', '', $request->phone) . '_' . $uniqueId . '@example.com';

            $user = User::create([
                'name' => $phoneOtp->name,
                'phone' => $request->phone,
                'phone_verified_at' => now(),
                'role' => 'user',
                'email' => $email,
                'password' => Hash::make($request->password), // Use provided password!
            ]);
        } else {
            $user->phone_verified_at = now();
            $user->save();
        }

        // Clean up the OTP record
        $phoneOtp->delete();

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
        // Create the redirect response but don't return it yet
        $redirectResponse = Socialite::driver('google')->redirect();
        
        // Get the redirect URL from the response
        $redirectUrl = $redirectResponse->getTargetUrl();
        
        // Log the actual URL being sent to Google
        Log::info('Actual Google OAuth URL: ' . $redirectUrl);
        
        // Extract and log just the redirect_uri parameter
        $parsedUrl = parse_url($redirectUrl);
        parse_str($parsedUrl['query'], $queryParams);
        Log::info('Redirect URI parameter sent to Google: ' . ($queryParams['redirect_uri'] ?? 'NOT FOUND'));
        
        return $redirectResponse;
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
            Log::error('Google OAuth Error: ' . $e->getMessage());
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