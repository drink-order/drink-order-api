<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductSize;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', 'user')->get();
        $productSizes = ProductSize::with(['product', 'product.toppings'])->get();
        
        // Create some sample orders
        for ($i = 1; $i <= 10; $i++) {
            $customer = $customers->random();
            $orderStatus = ['preparing', 'ready_for_pickup', 'completed'][rand(0, 2)];
            
            $order = Order::create([
                'user_id' => $customer->id,
                'total_price' => 0, // Will calculate below
                'order_status' => $orderStatus,
                'created_at' => now()->subDays(rand(0, 30)), // Random date within last 30 days
            ]);

            $totalPrice = 0;
            $itemCount = rand(1, 4); // 1-4 items per order

            for ($j = 0; $j < $itemCount; $j++) {
                $productSize = $productSizes->random();
                $quantity = rand(1, 3);
                
                $orderItem = $order->orderItems()->create([
                    'product_size_id' => $productSize->id,
                    'quantity' => $quantity,
                    'unit_price' => $productSize->price,
                ]);

                $itemPrice = $productSize->price * $quantity;
                
                // Add random toppings
                $availableToppings = $productSize->product->toppings;
                if ($availableToppings->count() > 0 && rand(0, 1)) {
                    $randomToppings = $availableToppings->random(min(2, $availableToppings->count()));
                    
                    foreach ($randomToppings as $productTopping) {
                        $orderItem->toppings()->create([
                            'topping_id' => $productTopping->topping_id,
                            'price' => $productTopping->price,
                        ]);
                        
                        $itemPrice += $productTopping->price * $quantity;
                    }
                }
                
                $totalPrice += $itemPrice;
            }

            // Update total price
            $order->update(['total_price' => $totalPrice]);
        }
    }
}