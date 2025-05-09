<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard stats based on user role
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        if ($user->isAdmin()) {
            return $this->adminDashboard();
        } elseif ($user->isShopOwner()) {
            return $this->shopOwnerDashboard();
        } elseif ($user->isStaff()) {
            return $this->staffDashboard();
        } else {
            return $this->userDashboard($user);
        }
    }
    
    /**
     * Admin dashboard stats
     */
    private function adminDashboard()
    {
        // Get counts
        $userCount = User::count();
        $categoryCount = Category::count();
        $productCount = Product::count();
        $orderCount = Order::count();
        
        // Get revenue stats
        $totalRevenue = Order::where('order_status', 'completed')->sum('total_price');
        
        // Recent orders
        $recentOrders = Order::with(['user', 'orderItems.productSize.product'])
            ->latest()
            ->take(5)
            ->get();
        
        // User registration stats by role
        $usersByRole = User::select('role', DB::raw('count(*) as count'))
            ->groupBy('role')
            ->get();
        
        // Order status stats
        $ordersByStatus = Order::select('order_status', DB::raw('count(*) as count'))
            ->groupBy('order_status')
            ->get();
        
        return response()->json([
            'counts' => [
                'users' => $userCount,
                'categories' => $categoryCount,
                'products' => $productCount,
                'orders' => $orderCount,
            ],
            'revenue' => [
                'total' => $totalRevenue,
            ],
            'recent_orders' => $recentOrders,
            'users_by_role' => $usersByRole,
            'orders_by_status' => $ordersByStatus,
        ]);
    }
    
    /**
     * Shop owner dashboard stats
     */
    private function shopOwnerDashboard()
    {
        // Product stats
        $totalProducts = Product::count();
        $availableProducts = Product::where('is_available', true)->count();
        
        // Order stats
        $totalOrders = Order::count();
        $completedOrders = Order::where('order_status', 'completed')->count();
        $pendingOrders = Order::where('order_status', 'pending')->count();
        
        // Revenue stats
        $totalRevenue = Order::where('order_status', 'completed')->sum('total_price');
        
        // Popular products
        $popularProducts = DB::table('order_items')
            ->join('product_sizes', 'order_items.product_size_id', '=', 'product_sizes.id')
            ->join('products', 'product_sizes.product_id', '=', 'products.id')
            ->select('products.id', 'products.name', DB::raw('SUM(order_items.quantity) as total_quantity'))
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_quantity', 'desc')
            ->take(5)
            ->get();
        
        // Recent orders
        $recentOrders = Order::with(['user', 'orderItems.productSize.product'])
            ->latest()
            ->take(5)
            ->get();
        
        return response()->json([
            'products' => [
                'total' => $totalProducts,
                'available' => $availableProducts,
            ],
            'orders' => [
                'total' => $totalOrders,
                'completed' => $completedOrders,
                'pending' => $pendingOrders,
            ],
            'revenue' => [
                'total' => $totalRevenue,
            ],
            'popular_products' => $popularProducts,
            'recent_orders' => $recentOrders,
        ]);
    }
    
    /**
     * Staff dashboard stats
     */
    private function staffDashboard()
    {
        // Order stats
        $totalOrders = Order::count();
        $pendingOrders = Order::where('order_status', 'pending')->count();
        $processingOrders = Order::where('order_status', 'processing')->count();
        
        // Pending orders that need attention
        $ordersNeedingAttention = Order::with(['user', 'orderItems.productSize.product'])
            ->where('order_status', 'pending')
            ->latest()
            ->take(10)
            ->get();
        
        return response()->json([
            'orders' => [
                'total' => $totalOrders,
                'pending' => $pendingOrders,
                'processing' => $processingOrders,
            ],
            'orders_needing_attention' => $ordersNeedingAttention,
        ]);
    }
    
    /**
     * Regular user dashboard stats
     */
    private function userDashboard(User $user)
    {
        // User order stats
        $totalOrders = Order::where('user_id', $user->id)->count();
        $pendingOrders = Order::where('user_id', $user->id)
            ->whereIn('order_status', ['pending', 'processing'])
            ->count();
        
        // Recent orders
        $recentOrders = Order::with(['orderItems.productSize.product'])
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get();
        
        return response()->json([
            'orders' => [
                'total' => $totalOrders,
                'pending' => $pendingOrders,
            ],
            'recent_orders' => $recentOrders,
        ]);
    }
}