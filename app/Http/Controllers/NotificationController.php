<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class NotificationController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware('auth:sanctum')
        ];
    }

    /**
     * Get notifications for the authenticated user with polling support
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = $user->notifications();
        
        // Filter by read status
        if ($request->has('read')) {
            $query->where('read', $request->boolean('read'));
        }
        
        // Support for limiting results (useful for polling)
        $limit = $request->input('limit', 50);
        
        // Get notifications with pagination support
        $notifications = $query->latest()
            ->limit($limit)
            ->get();
        
        // Get counts for frontend
        $unreadCount = $user->notifications()->where('read', false)->count();
        $totalCount = $user->notifications()->count();
        
        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'total_count' => $totalCount,
            'timestamp' => now()->toISOString(), // For polling comparison
        ]);
    }

    /**
     * Get only unread notifications count (lightweight endpoint for frequent polling)
     */
    public function getUnreadCount(Request $request)
    {
        $user = $request->user();
        $unreadCount = $user->notifications()->where('read', false)->count();
        
        return response()->json([
            'unread_count' => $unreadCount,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get latest notifications since timestamp (for efficient polling)
     */
    public function getLatest(Request $request)
    {
        $user = $request->user();
        $since = $request->input('since'); // ISO timestamp
        
        $query = $user->notifications();
        
        if ($since) {
            $query->where('created_at', '>', $since);
        }
        
        $notifications = $query->latest()->get();
        $unreadCount = $user->notifications()->where('read', false)->count();
        
        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'timestamp' => now()->toISOString(),
            'has_new' => $notifications->count() > 0,
        ]);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(Request $request, Notification $notification)
    {
        // Check if notification belongs to the user
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $notification->update(['read' => true]);
        
        // Return updated unread count
        $unreadCount = $request->user()->notifications()->where('read', false)->count();
        
        return response()->json([
            'notification' => $notification,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $user->notifications()->where('read', false)->update(['read' => true]);
        
        return response()->json([
            'message' => 'All notifications marked as read',
            'unread_count' => 0,
        ]);
    }
}