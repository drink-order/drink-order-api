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
     * Get notifications for the authenticated user
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = $user->notifications();
        
        // Filter by read status
        if ($request->has('read')) {
            $query->where('read', $request->boolean('read'));
        }
        
        // Sort by latest by default and get all without pagination
        $notifications = $query->latest()->get();
        
        return response()->json(['notifications' => $notifications]);
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
        
        return response()->json(['notification' => $notification]);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();
        $user->notifications()->where('read', false)->update(['read' => true]);
        
        return response()->json(['message' => 'All notifications marked as read']);
    }
}