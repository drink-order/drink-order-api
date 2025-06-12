<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Models\Notification;

class SendOrderStatusNotification
{
    /**
     * Handle the event.
     */
    public function handle(OrderStatusChanged $event): void
    {
        // Create user-friendly status message
        $statusMessage = match($event->newStatus) {
            'preparing' => 'is now being prepared',
            'ready_for_pickup' => 'is ready for pickup and payment',
            'completed' => 'has been completed',
            default => "has been updated to {$event->newStatus}"
        };

        $messageText = "Your order #{$event->order->id} {$statusMessage}.";

        // CHECK FOR DUPLICATES: Don't create if notification already exists for this order/status in last 2 minutes
        $existingNotification = Notification::where('user_id', $event->order->user_id)
            ->where('order_id', $event->order->id)
            ->where('message', $messageText)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->first();

        if ($existingNotification) {
            // Duplicate found - don't create another one
            return;
        }

        // Create notification for the order owner
        Notification::create([
            'title' => 'Order Status Updated',
            'message' => $messageText,
            'type' => 'order',
            'user_id' => $event->order->user_id,
            'order_id' => $event->order->id,
        ]);
    }
}