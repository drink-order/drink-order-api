<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
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
        
        // If starts with +855, keep as is
        if (str_starts_with($cleaned, '+855')) {
            return $cleaned;
        }
        
        // If starts with 855, add +
        if (str_starts_with($cleaned, '855')) {
            return '+' . $cleaned;
        }
        
        // If starts with 0 (Cambodian local format), replace with +855
        if (str_starts_with($cleaned, '0')) {
            return '+855' . substr($cleaned, 1);
        }
        
        // If just the number without country code (8-9 digits), add +855
        if (strlen($cleaned) >= 8 && strlen($cleaned) <= 9 && !str_contains($cleaned, '+')) {
            return '+855' . $cleaned;
        }
        
        // Return as is if none of the above patterns match
        return $cleaned;
    }

    // ==================== PROFILE MANAGEMENT ====================
    
    /**
     * Get current user's profile
     */
    public function getProfile(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    /**
     * Update current user's profile
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $request->user()->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'password' => 'sometimes|nullable|string|min:8|confirmed',
        ]);

        $user = $request->user();
        $updateData = [];

        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }
        
        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }
        
        if ($request->has('phone')) {
            // Check for unique phone number if provided
            if (!empty($request->phone)) {
                $normalizedPhone = $this->normalizePhoneNumber($request->phone);
                $existingPhone = User::where('phone', $normalizedPhone)
                    ->where('id', '!=', $user->id)
                    ->first();
                if ($existingPhone) {
                    throw ValidationException::withMessages([
                        'phone' => ['The phone number has already been taken.'],
                    ]);
                }
                $updateData['phone'] = $normalizedPhone;
            } else {
                $updateData['phone'] = null;
            }
        }
        
        if ($request->has('password') && !empty($request->password)) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json(['user' => $user->fresh()]);
    }

    // ==================== ADMIN USER MANAGEMENT ====================
    
    /**
     * Admin: Create user with any role
     */
    public function createUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable|string|max:20',
            'role' => 'required|in:admin,shop_owner,staff,user',
            'password' => 'required|string|min:8',
        ]);

        // Normalize and check phone uniqueness
        $normalizedPhone = null;
        if (!empty($request->phone)) {
            $normalizedPhone = $this->normalizePhoneNumber($request->phone);
            $existingPhone = User::where('phone', $normalizedPhone)->first();
            if ($existingPhone) {
                throw ValidationException::withMessages([
                    'phone' => ['The phone number has already been taken.'],
                ]);
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $normalizedPhone,
            'role' => $request->role,
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['user' => $user], 201);
    }

    /**
     * Admin: Get any user
     */
    public function getUser(User $user)
    {
        return response()->json(['user' => $user]);
    }

    /**
     * Admin: Update any user
     */
    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'role' => 'sometimes|required|in:admin,shop_owner,staff,user',
            'password' => 'sometimes|nullable|string|min:8',
        ]);

        $updateData = [];

        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }

        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }

        if ($request->has('role')) {
            $updateData['role'] = $request->role;
        }
        
        if ($request->has('phone')) {
            if (!empty($request->phone)) {
                $normalizedPhone = $this->normalizePhoneNumber($request->phone);
                $existingPhone = User::where('phone', $normalizedPhone)
                    ->where('id', '!=', $user->id)
                    ->first();
                if ($existingPhone) {
                    throw ValidationException::withMessages([
                        'phone' => ['The phone number has already been taken.'],
                    ]);
                }
                $updateData['phone'] = $normalizedPhone;
            } else {
                $updateData['phone'] = null;
            }
        }
        
        if ($request->has('password') && !empty($request->password)) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json(['user' => $user->fresh()]);
    }

    /**
     * Admin: Delete any user
     */
    public function deleteUser(User $user)
    {
        // Prevent deleting the last admin
        if ($user->role === 'admin') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'message' => 'Cannot delete the last admin user.'
                ], 422);
            }
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    // ==================== SHOP OWNER STAFF MANAGEMENT ====================
    
    /**
     * Shop Owner: Create staff member only
     */
    public function createStaff(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
        ]);

        // Normalize and check phone uniqueness
        $normalizedPhone = null;
        if (!empty($request->phone)) {
            $normalizedPhone = $this->normalizePhoneNumber($request->phone);
            $existingPhone = User::where('phone', $normalizedPhone)->first();
            if ($existingPhone) {
                throw ValidationException::withMessages([
                    'phone' => ['The phone number has already been taken.'],
                ]);
            }
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $normalizedPhone,
            'role' => 'staff', // Fixed to staff only
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['user' => $user], 201);
    }

    /**
     * Shop Owner: Get staff member
     */
    public function getStaff(User $user)
    {
        // Ensure only staff can be accessed by shop owners
        if ($user->role !== 'staff') {
            return response()->json(['message' => 'Can only access staff members'], 403);
        }

        return response()->json(['user' => $user]);
    }

    /**
     * Shop Owner: Update staff member
     */
    public function updateStaff(Request $request, User $user)
    {
        // Ensure only staff can be updated by shop owners
        if ($user->role !== 'staff') {
            return response()->json(['message' => 'Can only update staff members'], 403);
        }

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone' => 'sometimes|nullable|string|max:20',
            'password' => 'sometimes|nullable|string|min:8',
        ]);

        $updateData = [];

        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }

        if ($request->has('email')) {
            $updateData['email'] = $request->email;
        }
        
        if ($request->has('phone')) {
            if (!empty($request->phone)) {
                $normalizedPhone = $this->normalizePhoneNumber($request->phone);
                $existingPhone = User::where('phone', $normalizedPhone)
                    ->where('id', '!=', $user->id)
                    ->first();
                if ($existingPhone) {
                    throw ValidationException::withMessages([
                        'phone' => ['The phone number has already been taken.'],
                    ]);
                }
                $updateData['phone'] = $normalizedPhone;
            } else {
                $updateData['phone'] = null;
            }
        }
        
        if ($request->has('password') && !empty($request->password)) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json(['user' => $user->fresh()]);
    }

    /**
     * Shop Owner: Delete staff member
     */
    public function deleteStaff(User $user)
    {
        // Ensure only staff can be deleted by shop owners
        if ($user->role !== 'staff') {
            return response()->json(['message' => 'Can only delete staff members'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Staff member deleted successfully']);
    }
}