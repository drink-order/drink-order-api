<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ToppingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->get('/users', [AuthController::class, 'getAllUsers']);

// OTP routes with throttling
Route::middleware('throttle:otp')->group(function () {
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
});

Route::get('/test-twilio', function (App\Services\TwilioService $twilio) {
    return response()->json($twilio->testConnection());
});

// Invitation authentication route (public)
Route::get('/auth/invitation/{token}', [AuthController::class, 'handleInvitation']);

// Google OAuth routes
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Categories routes
    Route::apiResource('categories', CategoryController::class);
    
    // Admin-only routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/invitations', [InvitationController::class, 'index']);
        Route::post('/invitations', [InvitationController::class, 'store']);
        Route::get('/invitations/{token}/qrcode', [InvitationController::class, 'generateQrCode']);
        Route::delete('/invitations/{token}', [InvitationController::class, 'revoke']);
    });
    
    // Shop owner routes
    Route::middleware('role:shop_owner')->prefix('shop')->group(function () {
        // Add your shop owner routes here
    });
    
    // Staff routes
    Route::middleware('role:staff')->prefix('staff')->group(function () {
        // Add your staff routes here
    });

    // Product routes
    Route::apiResource('products', ProductController::class);

    // Topping routes
    Route::apiResource('toppings', ToppingController::class);

    // Order routes
    Route::apiResource('orders', OrderController::class)->except(['update', 'destroy']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);

    // Dashboard route
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth:sanctum');

    // Notification routes
    Route::get('/notifications', [NotificationController::class, 'index'])->middleware('auth:sanctum');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->middleware('auth:sanctum');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->middleware('auth:sanctum');
});