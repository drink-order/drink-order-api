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
        // Create notification for the order owner
        Notification::create([
            'title' => 'Order Status Updated',
            'message' => "Your order #{$event->order->id} status has been updated to {$event->newStatus}.",
            'type' => 'order',
            'user_id' => $event->order->user_id,
            'order_id' => $event->order->id,
        ]);
    }
}