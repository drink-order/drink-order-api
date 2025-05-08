<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

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
    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
        Route::get('/invitations', [App\Http\Controllers\Admin\InvitationController::class, 'index']);
        Route::post('/invitations', [App\Http\Controllers\Admin\InvitationController::class, 'store']);
        Route::get('/invitations/{token}/qrcode', [App\Http\Controllers\Admin\InvitationController::class, 'generateQrCode']);
        Route::delete('/invitations/{token}', [App\Http\Controllers\Admin\InvitationController::class, 'revoke']);
    });
    
    // Shop owner routes
    Route::middleware('role:shop_owner')->prefix('shop')->group(function () {
        // Add your shop owner routes here
    });
    
    // Staff routes
    Route::middleware('role:staff')->prefix('staff')->group(function () {
        // Add your staff routes here
    });
});