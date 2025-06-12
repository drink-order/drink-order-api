<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ToppingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Http\Request;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/test-supabase', [App\Http\Controllers\ProductController::class, 'testSupabase']);

// OTP routes with throttling
Route::middleware('throttle:otp')->group(function () {
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
});

Route::get('/test-twilio', function (App\Services\TwilioService $twilio) {
    return response()->json($twilio->testConnection());
});

// Public invitation authentication routes (no auth required for guests)
Route::get('/auth/invitation/{token}', [AuthController::class, 'handleInvitation']);
Route::post('/auth/invitation/{token}', [AuthController::class, 'handleInvitation']);

// Google OAuth routes
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Protected routes with guest session middleware
Route::middleware(['auth:sanctum', 'guest.session'])->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/users', [AuthController::class, 'getAllUsers']);
    
    // User profile routes (any authenticated user can edit their own profile)
    Route::get('/profile', [UserController::class, 'getProfile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    
    // Categories routes
    Route::apiResource('categories', CategoryController::class);
    
    // Admin-only routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Updated invitation management routes for table-based system
        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::post('/invitations', [InvitationController::class, 'createInvitation']); // Changed from 'store'
        Route::get('/invitations/{token}', [InvitationController::class, 'show']);
        Route::get('/invitations/{token}/qrcode', [InvitationController::class, 'generateQrCode']);
        Route::delete('/invitations/{token}', [InvitationController::class, 'revoke']);
        
        // Additional invitation management routes
        Route::get('/tables/{tableNumber}/invitations', [InvitationController::class, 'getTableInvitations']);
        Route::post('/invitations/bulk-revoke', [InvitationController::class, 'bulkRevoke']);
        Route::post('/invitations/cleanup', [InvitationController::class, 'cleanupExpired']);
        
        // Legacy user invitation route (for staff management)
        Route::post('/users/invite', [InvitationController::class, 'store']);
        
        // Admin user management - can manage any role
        Route::post('/users', [UserController::class, 'createUser']);
        Route::get('/users/{user}', [UserController::class, 'getUser']);
        Route::put('/users/{user}', [UserController::class, 'updateUser']);
        Route::delete('/users/{user}', [UserController::class, 'deleteUser']);
    });
    
    // Shop owner routes
    Route::middleware('role:shop_owner')->prefix('shop')->group(function () {
        // Shop owner can also create table invitations
        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::post('/invitations', [InvitationController::class, 'createInvitation']);
        Route::get('/invitations/{token}/qrcode', [InvitationController::class, 'generateQrCode']);
        Route::delete('/invitations/{token}', [InvitationController::class, 'revoke']);
        
        // Shop owner staff management - can only manage staff
        Route::post('/staff', [UserController::class, 'createStaff']);
        Route::get('/staff/{user}', [UserController::class, 'getStaff']);
        Route::put('/staff/{user}', [UserController::class, 'updateStaff']);
        Route::delete('/staff/{user}', [UserController::class, 'deleteStaff']);
    });
    
    // Staff routes
    Route::middleware('role:staff')->prefix('staff')->group(function () {
        // Staff can view invitations but not create/delete them
        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::get('/invitations/{token}', [InvitationController::class, 'show']);
        Route::get('/tables/{tableNumber}/invitations', [InvitationController::class, 'getTableInvitations']);
    });

    // Product routes
    Route::apiResource('products', ProductController::class);

    // Topping routes
    Route::apiResource('toppings', ToppingController::class);

    // Order routes
    Route::apiResource('orders', OrderController::class)->except(['update', 'destroy']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

    // Session-specific order route for guests
    Route::get('/orders/session/{sessionId}', [OrderController::class, 'getSessionOrders']);

    // Dashboard route
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
});