<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * OPTIMIZED: Create a notification with duplicate prevention
     */
    public function createNotification(array $data): Notification
    {
        // Validate required fields
        if (empty($data['title']) || empty($data['message']) || empty($data['user_id'])) {
            throw new \InvalidArgumentException('Title, message, and user_id are required');
        }

        try {
            // Use DB transaction for consistency
            return DB::transaction(function () use ($data) {
                // Check for duplicates using efficient query
                $exists = DB::table('notifications')
                    ->where('user_id', $data['user_id'])
                    ->where('title', $data['title'])
                    ->where('message', $data['message'])
                    ->where('created_at', '>=', now()->subMinutes(2))
                    ->exists();

                if ($exists) {
                    $existing = DB::table('notifications')
                        ->where('user_id', $data['user_id'])
                        ->where('title', $data['title'])
                        ->where('message', $data['message'])
                        ->where('created_at', '>=', now()->subMinutes(2))
                        ->first();
                    
                    Log::info('Duplicate notification prevented', ['existing_id' => $existing->id]);
                    return Notification::find($existing->id);
                }

                // Create notification using Eloquent for model events
                $notification = Notification::create([
                    'title' => $data['title'],
                    'message' => $data['message'],
                    'type' => $data['type'] ?? 'general',
                    'user_id' => $data['user_id'],
                    'order_id' => $data['order_id'] ?? null,
                    'read' => false,
                ]);

                // Clear user's notification cache
                Cache::forget("notification_count_user_{$data['user_id']}");

                Log::info('Notification created', [
                    'id' => $notification->id,
                    'user_id' => $notification->user_id,
                    'type' => $notification->type
                ]);

                return $notification;
            });
            
        } catch (\Exception $e) {
            Log::error('Notification creation failed', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send order status notification
     */
    public function sendOrderStatusNotification(Order $order, string $oldStatus, string $newStatus): Notification
    {
        $statusMessages = [
            'preparing' => 'is now being prepared by our team',
            'ready_for_pickup' => 'is ready for pickup! Please come to the counter',
            'completed' => 'has been completed. Thank you for your order!'
        ];

        $statusTitles = [
            'preparing' => 'Order Being Prepared',
            'ready_for_pickup' => '🎉 Order Ready for Pickup!',
            'completed' => 'Order Completed'
        ];

        $message = $statusMessages[$newStatus] ?? "status has been updated to {$newStatus}";
        $title = $statusTitles[$newStatus] ?? 'Order Status Updated';

        return $this->createNotification([
            'title' => $title,
            'message' => "Your order #{$order->order_number} {$message}",
            'type' => 'order',
            'user_id' => $order->user_id,
            'order_id' => $order->id,
        ]);
    }

    /**
     * Send custom notification
     */
    public function sendCustomNotification(User $user, string $title, string $message, string $type = 'general'): Notification
    {
        return $this->createNotification([
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'user_id' => $user->id,
        ]);
    }
}