<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderTopping;
use App\Models\ProductSize;
use App\Models\Topping;
use App\Events\OrderStatusChanged;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware('auth:sanctum')
        ];
    }

    /**
     * Display a listing of orders.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['orderItems.productSize.product', 'orderItems.toppings.topping']);
        
        // If user is not staff or admin, only show their orders
        if (!$user->isStaff() && !$user->isAdmin() && !$user->isShopOwner()) {
            $query->where('user_id', $user->id);
        }
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('order_status', $request->status);
        }
        
        // Sort by latest by default
        $query->latest();
        
        // Return all results without pagination
        $orders = $query->get();
        
        return response()->json(['orders' => $orders]);
    }

    /**
     * Store a newly created order.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_size_id' => 'required|exists:product_sizes,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.toppings' => 'nullable|array',
            'items.*.toppings.*.topping_id' => 'required|exists:toppings,id',
        ]);
        
    // Calculate total price and validate all items
    $totalPrice = 0;
    $orderItems = [];

    foreach ($validated['items'] as $item) {
        $productSize = ProductSize::with('product')->findOrFail($item['product_size_id']);
        
        // Check if product is available
        if (!$productSize->product->is_available) {
            return response()->json([
                'message' => "Product '{$productSize->product->name}' is not available"
            ], 422);
        }
        
        $itemPrice = $productSize->price * $item['quantity'];
        $toppingsData = [];
        
        // Calculate toppings price
        if (isset($item['toppings']) && !empty($item['toppings'])) {
            foreach ($item['toppings'] as $toppingItem) {
                $topping = Topping::findOrFail($toppingItem['topping_id']);
                
                // Check if topping is available
                if (!$topping->is_available) {
                    return response()->json([
                        'message' => "Topping '{$topping->name}' is not available"
                    ], 422);
                }
                
                // Get the specific price for this product-topping combination
                $productTopping = $productSize->product->toppings()
                    ->where('topping_id', $topping->id)
                    ->first();
                
                if (!$productTopping) {
                    return response()->json([
                        'message' => "Topping '{$topping->name}' is not available for product '{$productSize->product->name}'"
                    ], 422);
                }
                
                $toppingPrice = $productTopping->price;
                $itemPrice += $toppingPrice * $item['quantity'];
                
                $toppingsData[] = [
                    'topping_id' => $topping->id,
                    'price' => $toppingPrice
                ];
            }
        }
        
        $totalPrice += $itemPrice;
        
        $orderItems[] = [
            'product_size_id' => $productSize->id,
            'quantity' => $item['quantity'],
            'unit_price' => $productSize->price,
            'toppings' => $toppingsData
        ];
    }
        
        // Create order with transaction to ensure data integrity
        DB::beginTransaction();
        
        try {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'total_price' => $totalPrice,
                'order_status' => 'preparing'
            ]);
            
            // Create order items and toppings
            foreach ($orderItems as $itemData) {
                $orderItem = $order->orderItems()->create([
                    'product_size_id' => $itemData['product_size_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price']
                ]);
                
                // Create order toppings
                if (isset($itemData['toppings']) && !empty($itemData['toppings'])) {
                    foreach ($itemData['toppings'] as $toppingData) {
                        $orderItem->toppings()->create([
                            'topping_id' => $toppingData['topping_id'],
                            'price' => $toppingData['price']
                        ]);
                    }
                }
            }
            
            DB::commit();
            
            // Load relationships
            $order->load(['orderItems.productSize.product', 'orderItems.toppings.topping']);
            
            return response()->json(['order' => $order], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create order: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified order.
     */
    public function show(Request $request, Order $order)
    {
        $user = $request->user();
        
        // Check if user can view this order
        if (!$user->isStaff() && !$user->isAdmin() && !$user->isShopOwner() && $order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $order->load(['orderItems.productSize.product', 'orderItems.toppings.topping', 'user']);
        
        return response()->json(['order' => $order]);
    }

    /**
     * Update the order status (staff only).
     */
    public function updateStatus(Request $request, Order $order)
    {
        $user = $request->user();
        
        // Check if user is staff, admin, or shop owner
        if (!$user->isStaff() && !$user->isAdmin() && !$user->isShopOwner()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'order_status' => 'required|in:preparing,ready_for_pickup,completed',
        ]);
        
        $oldStatus = $order->order_status;
        $newStatus = $validated['order_status'];
        
        // Update order status
        $order->update(['order_status' => $newStatus]);
        
        // Fire event if status actually changed
        if ($oldStatus !== $newStatus) {
            event(new OrderStatusChanged($order, $user, $oldStatus, $newStatus));
        }
        
        return response()->json(['order' => $order]);
    }
}