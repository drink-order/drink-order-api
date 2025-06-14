<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }
    
    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Default API rate limiting
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // OTP rate limiting with both per-minute and daily limits
        RateLimiter::for('otp', function (Request $request) {
            return [
                Limit::perMinute(3)->by($request->ip()),
                Limit::perDay(10)->by($request->ip()),
            ];
        });

        // NEW: Notification polling rate limiting (for 30-second polling)
        RateLimiter::for('notification_polling', function (Request $request) {
            return [
                // Allow 2 requests per minute per user (every 30 seconds)
                Limit::perMinute(2)->by($request->user()?->id ?: $request->ip())
                    ->response(function () {
                        return response()->json([
                            'unread_count' => 0,
                            'error' => 'Too many requests. Please slow down.',
                            'retry_after' => 60
                        ], 429);
                    }),
                
                // Global limit per IP (prevents abuse from multiple users on same IP)
                Limit::perMinute(10)->by($request->ip()),
            ];
        });

        // General notification operations (less restrictive)
        RateLimiter::for('notifications', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });
    }
}