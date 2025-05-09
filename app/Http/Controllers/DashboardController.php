<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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
            return $this->shopOwnerDashboard($request);
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
    private function shopOwnerDashboard(Request $request)
    {
        // Get date range parameters
        $period = $request->input('period', 'all'); // Options: day, week, month, year, all, custom
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Build date query based on period
        $dateQuery = Order::query();
        if ($period === 'day') {
            $dateQuery->whereDate('created_at', Carbon::today());
        } elseif ($period === 'week') {
            $dateQuery->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
        } elseif ($period === 'month') {
            $dateQuery->whereMonth('created_at', Carbon::now()->month)
                    ->whereYear('created_at', Carbon::now()->year);
        } elseif ($period === 'year') {
            $dateQuery->whereYear('created_at', Carbon::now()->year);
        } elseif ($period === 'custom' && $startDate && $endDate) {
            $dateQuery->whereBetween('created_at', [Carbon::parse($startDate), Carbon::parse($endDate)]);
        }

        // Staff count
        $staffCount = User::where('role', 'staff')->count();
        
        // Product stats
        $totalProducts = Product::count();
        $availableProducts = Product::where('is_available', true)->count();
        
        // Order stats with time filtering
        $completedOrders = (clone $dateQuery)->where('order_status', 'completed')->count();
        $readyOrders = (clone $dateQuery)->where('order_status', 'ready_for_pickup')->count();
        $preparingOrders = (clone $dateQuery)->where('order_status', 'preparing')->count();
        
        // Revenue stats
        $totalRevenue = (clone $dateQuery)->where('order_status', 'completed')->sum('total_price');
        
        // Orders by date (for chart)
        $ordersByDate = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw("SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw('SUM(total_price) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        
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
            'staff_count' => $staffCount,
            'products' => [
                'total' => $totalProducts,
                'available' => $availableProducts,
            ],
            'orders' => [
                'total' => $completedOrders + $readyOrders + $preparingOrders,
                'completed' => $completedOrders,
                'ready_for_pickup' => $readyOrders,
                'preparing' => $preparingOrders,
            ],
            'revenue' => [
                'total' => $totalRevenue,
            ],
            'chart_data' => [
                'orders_by_date' => $ordersByDate
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
        $preparingOrders = Order::where('order_status', 'preparing')->count();
        $readyForPickupOrders = Order::where('order_status', 'ready_for_pickup')->count();

        // Orders that need attention
        $ordersNeedingAttention = Order::with(['user', 'orderItems.productSize.product'])
            ->where('order_status', 'preparing')
            ->latest()
            ->take(10)
            ->get();
        
        return response()->json([
            'orders' => [
                'total' => $totalOrders,
                'preparing' => $preparingOrders,
                'ready_for_pickup' => $readyForPickupOrders,
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
        $activeOrders = Order::where('user_id', $user->id)
            ->whereIn('order_status', ['preparing', 'ready_for_pickup'])
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
                'active' => $activeOrders,
            ],
            'recent_orders' => $recentOrders,
        ]);
    }
}