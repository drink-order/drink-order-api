<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Shop owner dashboard stats
     */
    public function index(Request $request)
    {
        // Get date range parameters
        $period = $request->input('period', 'all');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Build base date query
        $dateQuery = $this->buildDateQuery($period, $startDate, $endDate);

        // Staff count (not affected by date filter)
        $staffCount = User::where('role', 'staff')->count();
        
        // Product stats (not affected by date filter)
        $totalProducts = Product::count();
        $availableProducts = Product::where('is_available', true)->count();
        
        // Order stats with date filtering - only completed orders
        $completedOrders = (clone $dateQuery)->where('order_status', 'completed')->count();
        
        // Revenue stats with date filtering
        $totalRevenue = (clone $dateQuery)->where('order_status', 'completed')->sum('total_price');
        
        // Orders by date for chart (apply same date filter)
        $ordersByDateQuery = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw("SUM(CASE WHEN order_status = 'completed' THEN 1 ELSE 0 END) as completed"),
                DB::raw('SUM(CASE WHEN order_status = \'completed\' THEN total_price ELSE 0 END) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date');
            
        // Apply date filter to chart data
        $ordersByDate = $this->applyDateFilterToQuery($ordersByDateQuery, $period, $startDate, $endDate)->get();
        
        // Popular products with date filtering
        $popularProductsQuery = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('product_sizes', 'order_items.product_size_id', '=', 'product_sizes.id')
            ->join('products', 'product_sizes.product_id', '=', 'products.id')
            ->select('products.id', 'products.name', DB::raw('SUM(order_items.quantity) as total_quantity'))
            ->where('orders.order_status', 'completed')
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_quantity', 'desc')
            ->take(5);
            
        // Apply date filter to popular products
        $popularProducts = $this->applyDateFilterToQuery($popularProductsQuery, $period, $startDate, $endDate, 'orders.created_at')->get();
        
        // Recent orders with date filtering
        $recentOrdersQuery = Order::with(['user', 'orderItems.productSize.product'])
            ->where('order_status', 'completed')
            ->latest()
            ->take(10);
            
        $recentOrders = $this->applyDateFilterToQuery($recentOrdersQuery, $period, $startDate, $endDate)->get();
        
        return response()->json([
            'staff_count' => $staffCount,
            'products' => [
                'total' => $totalProducts,
                'available' => $availableProducts,
            ],
            'orders' => [
                'completed' => $completedOrders,
            ],
            'revenue' => [
                'total' => $totalRevenue,
            ],
            'chart_data' => [
                'orders_by_date' => $ordersByDate
            ],
            'popular_products' => $popularProducts,
            'recent_orders' => $recentOrders,
            'period_info' => [
                'period' => $period,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]
        ]);
    }
    
    /**
     * Build date query based on period
     */
    private function buildDateQuery($period, $startDate = null, $endDate = null)
    {
        $query = Order::query();
        return $this->applyDateFilterToQuery($query, $period, $startDate, $endDate);
    }
    
    /**
     * Apply date filter to any query
     */
    private function applyDateFilterToQuery($query, $period, $startDate = null, $endDate = null, $dateColumn = 'created_at')
    {
        switch ($period) {
            case 'day':
                $query->whereDate($dateColumn, Carbon::today());
                break;
                
            case 'week':
                $query->whereBetween($dateColumn, [
                    Carbon::now()->startOfWeek(),
                    Carbon::now()->endOfWeek()
                ]);
                break;
                
            case 'month':
                $query->whereMonth($dateColumn, Carbon::now()->month)
                      ->whereYear($dateColumn, Carbon::now()->year);
                break;
                
            case 'year':
                $query->whereYear($dateColumn, Carbon::now()->year);
                break;
                
            case 'custom':
                if ($startDate && $endDate) {
                    $start = Carbon::parse($startDate)->startOfDay();
                    $end = Carbon::parse($endDate)->endOfDay();
                    $query->whereBetween($dateColumn, [$start, $end]);
                }
                break;
                
            // 'all' - no date filter applied
        }
        
        return $query;
    }
}