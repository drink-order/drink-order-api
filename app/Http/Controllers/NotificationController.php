<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class NotificationController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware('auth:sanctum'),
            new Middleware('throttle:notification_polling')->only(['getUnreadCount', 'getLatest']),
        ];
    }

    /**
     * Get notifications for the authenticated user with polling support
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Use efficient query builder instead of Eloquent relationships
        $query = DB::table('notifications')->where('user_id', $user->id);
        
        // Filter by read status
        if ($request->has('read')) {
            $query->where('read', $request->boolean('read'));
        }
        
        // Support for limiting results (useful for polling)
        $limit = min($request->input('limit', 50), 100); // Cap at 100
        
        // Get notifications with efficient query
        $notifications = $query->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get([
                'id', 'title', 'message', 'type', 'read', 
                'order_id', 'created_at', 'updated_at'
            ]);
        
        // Get counts efficiently with single query
        $counts = DB::table('notifications')
            ->selectRaw('
                COUNT(*) as total_count,
                COUNT(CASE WHEN read = false THEN 1 END) as unread_count
            ')
            ->where('user_id', $user->id)
            ->first();
        
        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $counts->unread_count ?? 0,
            'total_count' => $counts->total_count ?? 0,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * OPTIMIZED: Get only unread notifications count (lightweight endpoint for frequent polling)
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $cacheKey = "notification_count_user_{$user->id}";
            
            // Cache for 15 seconds to reduce DB load while keeping freshness
            $unreadCount = Cache::remember($cacheKey, 15, function () use ($user) {
                return DB::table('notifications')
                    ->where('user_id', $user->id)
                    ->where('read', false)
                    ->count();
            });
            
            return response()->json([
                'unread_count' => $unreadCount,
                'timestamp' => now()->toISOString(),
            ], 200, [
                // Headers to prevent caching by browsers/proxies
                'Cache-Control' => 'no-cache, no-store, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
            
        } catch (\Exception $e) {
            Log::error('Notification count error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'unread_count' => 0,
                'timestamp' => now()->toISOString(),
                'error' => 'Failed to fetch count'
            ], 500);
        }
    }

    /**
     * OPTIMIZED: Get latest notifications since timestamp (for efficient polling)
     */
    public function getLatest(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $since = $request->input('since');
            $limit = min($request->input('limit', 20), 50); // Cap limit
            
            // Build efficient query
            $query = DB::table('notifications')
                ->select([
                    'id', 'title', 'message', 'type', 'read', 
                    'order_id', 'created_at', 'updated_at'
                ])
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit);
            
            if ($since) {
                try {
                    $sinceDate = \Carbon\Carbon::parse($since);
                    $query->where('created_at', '>', $sinceDate);
                } catch (\Exception $e) {
                    Log::warning('Invalid since parameter', ['since' => $since]);
                }
            }
            
            $notifications = $query->get();
            
            // Get unread count with separate optimized query
            $unreadCount = DB::table('notifications')
                ->where('user_id', $user->id)
                ->where('read', false)
                ->count();
            
            // Clear cache when fetching latest (data might have changed)
            Cache::forget("notification_count_user_{$user->id}");
            
            return response()->json([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
                'timestamp' => now()->toISOString(),
                'has_new' => $notifications->count() > 0,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Notification latest error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'notifications' => [],
                'unread_count' => 0,
                'timestamp' => now()->toISOString(),
                'has_new' => false,
                'error' => 'Failed to fetch notifications'
            ], 500);
        }
    }

    /**
     * OPTIMIZED: Mark notification as read
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        try {
            // Check if notification belongs to the user
            if ($notification->user_id !== $request->user()->id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            
            // Use query builder for efficiency
            DB::table('notifications')
                ->where('id', $notification->id)
                ->where('user_id', $request->user()->id)
                ->update(['read' => true, 'updated_at' => now()]);
            
            // Clear cache after update
            Cache::forget("notification_count_user_{$request->user()->id}");
            
            // Get updated unread count
            $unreadCount = DB::table('notifications')
                ->where('user_id', $request->user()->id)
                ->where('read', false)
                ->count();
            
            return response()->json([
                'success' => true,
                'unread_count' => $unreadCount,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Mark as read error', [
                'notification_id' => $notification->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark as read'
            ], 500);
        }
    }

    /**
     * OPTIMIZED: Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Use efficient bulk update
            $updated = DB::table('notifications')
                ->where('user_id', $user->id)
                ->where('read', false)
                ->update(['read' => true, 'updated_at' => now()]);
            
            // Clear cache
            Cache::forget("notification_count_user_{$user->id}");
            
            return response()->json([
                'message' => 'All notifications marked as read',
                'unread_count' => 0,
                'updated_count' => $updated,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Mark all as read error', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark all as read'
            ], 500);
        }
    }

    /**
     * Send test notification from backend service
     */
    public function sendTest(Request $request): JsonResponse
    {
        $user = $request->user();
        $notificationService = app(\App\Services\NotificationService::class);
        
        try {
            $notification = $notificationService->sendCustomNotification(
                $user,
                'Backend Test 🎉',
                'This notification was created by the backend service and should appear in your frontend!',
                'system'
            );
            
            // Clear cache to ensure new notification appears immediately
            Cache::forget("notification_count_user_{$user->id}");
            
            return response()->json([
                'success' => true,
                'message' => 'Test notification sent from backend service',
                'notification' => $notification,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Send test notification error', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}