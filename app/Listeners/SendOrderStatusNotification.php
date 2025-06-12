<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\NotificationService;
use App\Models\Notification;

class SendOrderStatusNotification
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(OrderStatusChanged $event): void
    {
        try {
            // Use the service to create notification
            $this->notificationService->sendOrderStatusNotification(
                $event->order,
                $event->oldStatus,
                $event->newStatus
            );
        } catch (\Exception $e) {
            // Fallback to your original method
            $this->createFallbackNotification($event);
        }
    }

    /**
     * Fallback notification (your original code)
     */
    private function createFallbackNotification(OrderStatusChanged $event): void
    {
        $statusMessage = match($event->newStatus) {
            'preparing' => 'is now being prepared',
            'ready_for_pickup' => 'is ready for pickup and payment',
            'completed' => 'has been completed',
            default => "has been updated to {$event->newStatus}"
        };

        $messageText = "Your order #{$event->order->order_number} {$statusMessage}.";

        $existingNotification = Notification::where('user_id', $event->order->user_id)
            ->where('order_id', $event->order->id)
            ->where('message', $messageText)
            ->where('created_at', '>=', now()->subMinutes(2))
            ->first();

        if (!$existingNotification) {
            Notification::create([
                'title' => 'Order Status Updated',
                'message' => $messageText,
                'type' => 'order',
                'user_id' => $event->order->user_id,
                'order_id' => $event->order->id,
            ]);
        }
    }
}