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

        // Create notification for the order owner
        Notification::create([
            'title' => 'Order Status Updated',
            'message' => "Your order #{$event->order->id} {$statusMessage}.",
            'type' => 'order',
            'user_id' => $event->order->user_id,
            'order_id' => $event->order->id,
        ]);
    }
}