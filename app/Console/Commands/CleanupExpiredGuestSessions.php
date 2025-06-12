<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Order;
use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

class CleanupExpiredGuestSessions extends Command
{
    protected $signature = 'guests:cleanup-sessions';
    protected $description = 'Clean up expired guest sessions and incomplete orders';

    public function handle()
    {
        // Clean up expired guest tokens
        $expiredTokens = PersonalAccessToken::where('name', 'like', 'session_%')
            ->get()
            ->filter(function ($token) {
                $abilities = collect($token->abilities);
                $expirationAbility = $abilities->first(function ($ability) {
                    return str_starts_with($ability, 'expires_at:');
                });
                
                if ($expirationAbility) {
                    $timestamp = str_replace('expires_at:', '', $expirationAbility);
                    return now()->timestamp > $timestamp;
                }
                
                return false;
            });

        foreach ($expiredTokens as $token) {
            $token->delete();
        }

        // Clean up incomplete orders from expired sessions
        $incompleteOrders = Order::where('order_status', 'preparing')
            ->where('created_at', '<', now()->subHours(3))
            ->whereHas('user', function($q) {
                $q->where('role', 'guest');
            })
            ->count();

        if ($incompleteOrders > 0) {
            Order::where('order_status', 'preparing')
                ->where('created_at', '<', now()->subHours(3))
                ->whereHas('user', function($q) {
                    $q->where('role', 'guest');
                })
                ->delete();
        }

        $this->info("Cleaned up {$expiredTokens->count()} expired tokens and {$incompleteOrders} incomplete orders");
    }
}