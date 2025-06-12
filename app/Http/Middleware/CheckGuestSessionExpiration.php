<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CheckGuestSessionExpiration
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if ($user && $user->role === 'guest') {
            $token = $request->user()->currentAccessToken();
            
            // Check if token has custom expiration in abilities
            $expirationAbility = collect($token->abilities)->first(function ($ability) {
                return str_starts_with($ability, 'expires_at:');
            });
            
            if ($expirationAbility) {
                $expirationTimestamp = str_replace('expires_at:', '', $expirationAbility);
                $expiresAt = Carbon::createFromTimestamp($expirationTimestamp);
                
                if ($expiresAt < now()) {
                    // Delete the expired token
                    $token->delete();
                    
                    return response()->json([
                        'message' => 'Guest session has expired. Please scan QR code again.',
                        'session_expired' => true
                    ], 401);
                }
            }
        }
        
        return $next($request);
    }
}