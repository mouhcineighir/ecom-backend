<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $from = $request->query('from');
        $to = $request->query('to');
        $period = $request->query('period', '7days');

        $ordersQuery = Order::query();

        if ($from) {
            $ordersQuery->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $ordersQuery->whereDate('created_at', '<=', $to);
        }

        // Status counts
        $statusCounts = (clone $ordersQuery)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $totalOrders = $statusCounts->sum();

        $deliveredOrderIds = (clone $ordersQuery)
            ->where('status', 'delivered')
            ->pluck('id');

        $totalRevenue = Order::whereIn('id', $deliveredOrderIds)->sum('total');

        $totalProfit = OrderItem::whereIn('order_id', $deliveredOrderIds)
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->selectRaw('SUM((order_items.price - products.cost_price) * order_items.quantity) as profit')
            ->value('profit');

        // Customers count
        $customersCount = Customer::count();

        // Timeline Sales Chart Data (7 days, 30 days, 90 days)
        $days = 7;
        if ($period === '30days') $days = 30;
        elseif ($period === '90days') $days = 90;

        $startDate = $from ? Carbon::parse($from) : Carbon::now()->subDays($days - 1);
        $endDate = $to ? Carbon::parse($to) : Carbon::now();

        // Calculate previous period for REAL percentage comparisons
        $diffDays = $startDate->diffInDays($endDate) + 1;
        $prevStartDate = (clone $startDate)->subDays($diffDays);
        $prevEndDate = (clone $startDate)->subDay();

        // Real Revenue comparison
        $currentRevenue = Order::where('status', 'delivered')
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->sum('total');
        $prevRevenue = Order::where('status', 'delivered')
            ->whereBetween('created_at', [$prevStartDate->startOfDay(), $prevEndDate->endOfDay()])
            ->sum('total');

        $revenueChangePct = $prevRevenue > 0
            ? (($currentRevenue - $prevRevenue) / $prevRevenue) * 100
            : ($currentRevenue > 0 ? 100 : 0);

        // Real Orders comparison
        $currentPeriodOrders = Order::whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])->count();
        $prevPeriodOrders = Order::whereBetween('created_at', [$prevStartDate->startOfDay(), $prevEndDate->endOfDay()])->count();

        $ordersChangePct = $prevPeriodOrders > 0
            ? (($currentPeriodOrders - $prevPeriodOrders) / $prevPeriodOrders) * 100
            : ($currentPeriodOrders > 0 ? 100 : 0);

        // Real Customers comparison
        $currentCustomers = Customer::whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])->count();
        $prevCustomers = Customer::whereBetween('created_at', [$prevStartDate->startOfDay(), $prevEndDate->endOfDay()])->count();

        $customersChangePct = $prevCustomers > 0
            ? (($currentCustomers - $prevCustomers) / $prevCustomers) * 100
            : ($currentCustomers > 0 ? 100 : 0);

        // Products stats & Real Low Stock (stock <= 3)
        $totalProducts = Product::count();
        $inStockProducts = Product::where('stock', '>', 0)->count();
        $lowStockCount = Product::where('stock', '<=', 3)->count();

        $lowStockProducts = Product::with('images')
            ->where('stock', '<=', 3)
            ->orderBy('stock', 'asc')
            ->take(5)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'stock' => $p->stock,
                    'image' => $p->images->first()?->image,
                ];
            });

        $chartData = [];
        $current = clone $startDate;

        // Fetch daily grouped stats
        $dailyRevenues = Order::where('status', 'delivered')
            ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue')
            ->groupBy('date')
            ->pluck('revenue', 'date');

        $dailyOrders = Order::whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders_count')
            ->groupBy('date')
            ->pluck('orders_count', 'date');

        while ($current->lte($endDate)) {
            $dateStr = $current->format('Y-m-d');
            $formattedDate = $current->translatedFormat('d M');

            $rev = (float) ($dailyRevenues[$dateStr] ?? 0);
            $ord = (int) ($dailyOrders[$dateStr] ?? 0);

            $chartData[] = [
                'date' => $formattedDate,
                'full_date' => $dateStr,
                'revenue' => $rev,
                'orders' => $ord,
            ];

            $current->addDay();
        }

        // Filtered order IDs in scope
        $scopedOrderIds = (clone $ordersQuery)->pluck('id');

        // Per-product performance breakdown
        $allProducts = Product::with('images')->get();
        $perProductPerformance = $allProducts->map(function ($prod) use ($scopedOrderIds) {
            $itemsQuery = OrderItem::where('product_id', $prod->id)
                ->whereIn('order_id', $scopedOrderIds);

            $totalOrdersForProd = (clone $itemsQuery)->distinct('order_id')->count('order_id');

            $confirmedOrdersForProd = (clone $itemsQuery)
                ->whereHas('order', function ($q) {
                    $q->whereIn('status', ['confirmed', 'shipped', 'delivered']);
                })
                ->distinct('order_id')
                ->count('order_id');

            $deliveredOrdersForProd = (clone $itemsQuery)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'delivered');
                })
                ->distinct('order_id')
                ->count('order_id');

            $deliveredRevenueForProd = (clone $itemsQuery)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'delivered');
                })
                ->selectRaw('SUM(price * quantity) as total_rev')
                ->value('total_rev') ?? 0;

            $unitsSold = (clone $itemsQuery)
                ->whereHas('order', function ($q) {
                    $q->where('status', 'delivered');
                })
                ->sum('quantity');

            $coverImg = $prod->images->first()?->image;

            return [
                'id' => $prod->id,
                'name' => $prod->name,
                'sku' => $prod->sku,
                'image' => $coverImg,
                'stock' => $prod->stock,
                'total_orders' => $totalOrdersForProd,
                'confirmed_orders' => $confirmedOrdersForProd,
                'delivered_orders' => $deliveredOrdersForProd,
                'units_sold' => (int) $unitsSold,
                'delivered_revenue' => (float) $deliveredRevenueForProd,
            ];
        })->sortByDesc('delivered_orders')->values();

        // 8 Recent orders with eager loaded product images
        $recentOrders = Order::with(['customer', 'items.product.images'])
            ->orderBy('created_at', 'desc')
            ->take(8)
            ->get()
            ->map(function ($o) {
                $firstItem = $o->items->first();
                $product = $firstItem?->product;
                $coverImage = $product?->images->first()?->image;

                return [
                    'id' => $o->id,
                    'customer' => $o->customer,
                    'created_at' => $o->created_at,
                    'status' => $o->status,
                    'total' => (float) $o->total,
                    'items' => [
                        [
                            'id' => $firstItem?->id,
                            'quantity' => $firstItem?->quantity ?? 1,
                            'product' => [
                                'id' => $product?->id,
                                'name' => $product?->name ?? 'Produit',
                                'image' => $coverImage,
                            ],
                        ]
                    ],
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => [
                    'total' => $totalOrders,
                    'pending' => $statusCounts['pending'] ?? 0,
                    'confirmed' => $statusCounts['confirmed'] ?? 0,
                    'shipped' => $statusCounts['shipped'] ?? 0,
                    'delivered' => $statusCounts['delivered'] ?? 0,
                    'cancelled' => $statusCounts['cancelled'] ?? 0,
                    'returned' => $statusCounts['returned'] ?? 0,
                ],
                'revenue' => round($totalRevenue, 2),
                'profit' => round($totalProfit ?? 0, 2),
                'customers_count' => $customersCount,
                'changes' => [
                    'revenue_pct' => round($revenueChangePct, 1),
                    'orders_pct' => round($ordersChangePct, 1),
                    'customers_pct' => round($customersChangePct, 1),
                ],
                'products' => [
                    'total' => $totalProducts,
                    'in_stock' => $inStockProducts,
                    'low_stock_count' => $lowStockCount,
                    'total_stock_units' => (int) Product::sum('stock'),
                ],
                'sales_chart' => $chartData,
                'low_stock_products' => $lowStockProducts,
                'per_product_performance' => $perProductPerformance,
                'recent_orders' => $recentOrders,
            ],
        ]);
    }
}

