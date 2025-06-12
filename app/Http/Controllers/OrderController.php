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
use Illuminate\Support\Facades\Log;

class OrderController extends Controller implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware('auth:sanctum')
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Order::with(['orderItems.productSize.product', 'orderItems.toppings.topping']);
        
        if (!$user->isStaff() && !$user->isAdmin() && !$user->isShopOwner()) {
            $query->where('user_id', $user->id);
        }
        
        if ($request->has('status')) {
            $query->where('order_status', $request->status);
        }
        
        $query->latest();
        $orders = $query->get();
        
        return response()->json(['orders' => $orders]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_size_id' => 'required|exists:product_sizes,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.sugar_level' => 'required|in:0%,25%,50%,75%,100%', // Add sugar level validation
            'items.*.toppings' => 'nullable|array',
            'items.*.toppings.*.topping_id' => 'required|exists:toppings,id',
            'customer_name' => 'required_if:user_role,guest|string|max:50',
            'session_id' => 'nullable|string',
            'table_number' => 'required_if:user_role,guest|integer|min:1', // Add table number validation
        ]);

        $user = $request->user();
        
        // Enhanced guest user validation
        if ($user->isGuest()) {
            if (!isset($validated['session_id']) || !isset($validated['table_number'])) {
                return response()->json([
                    'message' => 'Session ID and table number are required for guest orders'
                ], 422);
            }
            
            $existingOrder = Order::where('user_id', $user->id)
                ->where('session_id', $validated['session_id'])
                ->whereIn('order_status', ['preparing', 'ready_for_pickup'])
                ->first();

            if ($existingOrder) {
                return response()->json([
                    'message' => 'You already have an active order in this session',
                    'existing_order' => $existingOrder->load(['orderItems.productSize.product'])
                ], 409);
            }
        }

        $totalPrice = 0;
        $orderItems = [];

        foreach ($validated['items'] as $item) {
            $productSize = ProductSize::with('product')->findOrFail($item['product_size_id']);
            
            if (!$productSize->product->is_available) {
                return response()->json([
                    'message' => "Product '{$productSize->product->name}' is not available"
                ], 422);
            }
            
            $itemPrice = $productSize->price * $item['quantity'];
            $toppingsData = [];
            
            if (isset($item['toppings']) && !empty($item['toppings'])) {
                foreach ($item['toppings'] as $toppingItem) {
                    $topping = Topping::findOrFail($toppingItem['topping_id']);
                    
                    if (!$topping->is_available) {
                        return response()->json([
                            'message' => "Topping '{$topping->name}' is not available"
                        ], 422);
                    }
                    
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
                'sugar_level' => $item['sugar_level'], // Add sugar level
                'toppings' => $toppingsData
            ];
        }
        
        DB::beginTransaction();
        
        try {
            $order = Order::create([
                'user_id' => $user->id,
                'session_id' => $validated['session_id'] ?? null,
                'customer_name' => $validated['customer_name'] ?? $user->name,
                'total_price' => $totalPrice,
                'order_status' => 'preparing'
            ]);
            
            foreach ($orderItems as $itemData) {
                $orderItem = $order->orderItems()->create([
                    'product_size_id' => $itemData['product_size_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'sugar_level' => $itemData['sugar_level'] // Add sugar level
                ]);
                
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
            
            $order->load(['orderItems.productSize.product', 'orderItems.toppings.topping']);
            
            Log::info('Order created', [
                'order_id' => $order->id,
                'session_id' => $validated['session_id'] ?? 'none',
                'customer_name' => $validated['customer_name'] ?? $user->name,
                'table_number' => $validated['table_number'] ?? 'none'
            ]);
            
            $response = ['order' => $order];
            if ($user->isGuest()) {
                $response['pickup_info'] = [
                    'order_number' => $order->order_number,
                    'table_number' => $validated['table_number'],
                    'message' => 'Please proceed to counter for payment and wait for pickup notification.'
                ];
            }
            
            return response()->json($response, 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order creation failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create order: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, Order $order)
    {
        $user = $request->user();
        
        if (!$user->isStaff() && !$user->isAdmin() && !$user->isShopOwner() && $order->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $order->load(['orderItems.productSize.product', 'orderItems.toppings.topping', 'user']);
        
        return response()->json(['order' => $order]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $user = $request->user();
        
        if (!$user->isStaff() && !$user->isAdmin() && !$user->isShopOwner()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'order_status' => 'required|in:preparing,ready_for_pickup,completed',
        ]);
        
        $oldStatus = $order->order_status;
        $newStatus = $validated['order_status'];
        
        $order->update(['order_status' => $newStatus]);
        
        if ($oldStatus !== $newStatus) {
            event(new OrderStatusChanged($order, $user, $oldStatus, $newStatus));
        }
        
        return response()->json(['order' => $order]);
    }

    public function getSessionOrders(Request $request, $sessionId)
    {
        $user = $request->user();
        
        if (!$user->isGuest()) {
            return response()->json(['message' => 'This endpoint is for guest users only'], 403);
        }
        
        $orders = Order::where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->with(['orderItems.productSize.product', 'orderItems.toppings.topping'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json(['orders' => $orders]);
    }
}