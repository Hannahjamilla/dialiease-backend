<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Patient;
use App\Models\User;
use App\Models\MedicalSupply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PatientOrdersController extends Controller
{
    /**
     * Get all patient orders with pagination and filters
     */
    public function getAllOrders(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);
            $status = $request->get('status', 'all');
            $date = $request->get('date', '');
            $search = $request->get('search', '');

            Log::info('Fetching all patient orders', [
                'per_page' => $perPage,
                'page' => $page,
                'status' => $status,
                'date' => $date,
                'search' => $search
            ]);

            // Build query with proper table aliases
            $query = Order::with([
                'patient.user',
                'orderItems.medicalSupply',
                'payment'
            ])
            ->select([
                'orders.*',
                'patients.hospitalNumber',
                'users.first_name',
                'users.last_name',
                'users.email'
            ])
            ->leftJoin('patients', 'orders.patientID', '=', 'patients.patientID')
            ->leftJoin('users', 'patients.userID', '=', 'users.userID');

            // Apply filters
            if ($status !== 'all') {
                $query->where('orders.order_status', $status);
            }

            if ($date) {
                $query->whereDate('orders.order_date', $date);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('users.first_name', 'like', "%{$search}%")
                      ->orWhere('users.last_name', 'like', "%{$search}%")
                      ->orWhere('patients.hospitalNumber', 'like', "%{$search}%")
                      ->orWhere('orders.orderID', 'like', "%{$search}%")
                      ->orWhere('orders.payment_reference', 'like', "%{$search}%");
                });
            }

            // Get total counts for analytics
            $totalOrders = Order::count();
            $pendingOrders = Order::where('order_status', 'pending')->count();
            $confirmedOrders = Order::where('order_status', 'confirmed')->count();
            $readyOrders = Order::where('order_status', 'ready_for_pickup')->count();
            $completedOrders = Order::where('order_status', 'completed')->count();
            $cancelledOrders = Order::where('order_status', 'cancelled')->count();

            // Execute query with pagination
            $orders = $query->orderBy('orders.created_at', 'desc')
                          ->paginate($perPage, ['*'], 'page', $page);

            // Transform the data
            $transformedOrders = $orders->getCollection()->map(function($order) {
                return $this->transformOrder($order);
            });

            $paginationData = [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ];

            Log::info('Successfully fetched orders', [
                'total_orders' => $orders->total(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage()
            ]);

            return response()->json([
                'success' => true,
                'orders' => $transformedOrders,
                'pagination' => $paginationData,
                'analytics' => [
                    'total_orders' => $totalOrders,
                    'pending_orders' => $pendingOrders,
                    'confirmed_orders' => $confirmedOrders,
                    'ready_orders' => $readyOrders,
                    'completed_orders' => $completedOrders,
                    'cancelled_orders' => $cancelledOrders,
                ],
                'message' => 'Orders fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching patient orders: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get orders grouped by date
     */
    public function getOrdersGroupedByDate(Request $request)
    {
        try {
            $date = $request->get('date', Carbon::today()->toDateString());
            $status = $request->get('status', 'all');

            Log::info('Fetching orders grouped by date', [
                'date' => $date,
                'status' => $status
            ]);

            $query = Order::with([
                'patient.user',
                'orderItems.medicalSupply',
                'payment'
            ])
            ->whereDate('order_date', $date);

            if ($status !== 'all') {
                $query->where('order_status', $status);
            }

            $orders = $query->orderBy('created_at', 'desc')->get();

            $transformedOrders = $orders->map(function($order) {
                return $this->transformOrder($order);
            });

            // Get date statistics
            $dateStats = [
                'total_orders' => $orders->count(),
                'total_amount' => $orders->sum('total_amount'),
                'status_breakdown' => $orders->groupBy('order_status')->map->count()
            ];

            return response()->json([
                'success' => true,
                'orders' => $transformedOrders,
                'date_stats' => $dateStats,
                'selected_date' => $date,
                'message' => 'Orders grouped by date fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching orders by date: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders by date: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order details by ID
     */
    public function getOrderDetails($orderID)
    {
        try {
            Log::info('Fetching order details', ['order_id' => $orderID]);

            $order = Order::with([
                'patient.user',
                'orderItems.medicalSupply',
                'payment'
            ])->find($orderID);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $transformedOrder = $this->transformOrder($order, true);

            return response()->json([
                'success' => true,
                'order' => $transformedOrder,
                'message' => 'Order details fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching order details: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(Request $request, $orderID)
    {
        DB::beginTransaction();

        try {
            $validated = $request->validate([
                'status' => 'required|in:pending,confirmed,ready_for_pickup,completed,cancelled',
                'notes' => 'nullable|string'
            ]);

            Log::info('Updating order status', [
                'order_id' => $orderID,
                'new_status' => $validated['status'],
                'notes' => $validated['notes'] ?? ''
            ]);

            $order = Order::find($orderID);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $oldStatus = $order->order_status;
            $order->update([
                'order_status' => $validated['status'],
                'updated_at' => now()
            ]);

            // Log the status change
            Log::info('Order status updated', [
                'order_id' => $orderID,
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
                'updated_by' => auth()->id() ?? 'system'
            ]);

            DB::commit();

            // Fetch updated order with relationships
            $updatedOrder = Order::with([
                'patient.user',
                'orderItems.medicalSupply',
                'payment'
            ])->find($orderID);

            return response()->json([
                'success' => true,
                'order' => $this->transformOrder($updatedOrder),
                'message' => 'Order status updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating order status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get orders statistics for dashboard
     */
    public function getOrdersStatistics()
    {
        try {
            // Today's statistics
            $today = Carbon::today();
            $todayOrders = Order::whereDate('order_date', $today)->count();
            $todayRevenue = Order::whereDate('order_date', $today)
                ->where('payment_status', 'paid')
                ->sum('total_amount');

            // Weekly statistics
            $weekStart = Carbon::now()->startOfWeek();
            $weekEnd = Carbon::now()->endOfWeek();
            $weeklyOrders = Order::whereBetween('order_date', [$weekStart, $weekEnd])->count();
            $weeklyRevenue = Order::whereBetween('order_date', [$weekStart, $weekEnd])
                ->where('payment_status', 'paid')
                ->sum('total_amount');

            // Monthly statistics
            $monthStart = Carbon::now()->startOfMonth();
            $monthEnd = Carbon::now()->endOfMonth();
            $monthlyOrders = Order::whereBetween('order_date', [$monthStart, $monthEnd])->count();
            $monthlyRevenue = Order::whereBetween('order_date', [$monthStart, $monthEnd])
                ->where('payment_status', 'paid')
                ->sum('total_amount');

            // Status breakdown
            $statusBreakdown = Order::select('order_status', DB::raw('COUNT(*) as count'))
                ->groupBy('order_status')
                ->get()
                ->pluck('count', 'order_status');

            // Recent orders (last 7 days)
            $recentOrders = Order::with(['patient.user'])
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function($order) {
                    return [
                        'id' => $order->orderID,
                        'reference' => 'ORD-' . $order->created_at->format('YmdHis') . '-' . str_pad($order->orderID, 6, '0', STR_PAD_LEFT),
                        'patient_name' => $order->patient->user->first_name . ' ' . $order->patient->user->last_name,
                        'total_amount' => (float) $order->total_amount,
                        'status' => $order->order_status,
                        'order_date' => $order->order_date?->toISOString()
                    ];
                });

            return response()->json([
                'success' => true,
                'statistics' => [
                    'today' => [
                        'orders' => $todayOrders,
                        'revenue' => (float) $todayRevenue
                    ],
                    'weekly' => [
                        'orders' => $weeklyOrders,
                        'revenue' => (float) $weeklyRevenue
                    ],
                    'monthly' => [
                        'orders' => $monthlyOrders,
                        'revenue' => (float) $monthlyRevenue
                    ],
                    'status_breakdown' => $statusBreakdown,
                    'recent_orders' => $recentOrders
                ],
                'message' => 'Statistics fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching orders statistics: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales analytics data
     */
    public function getSalesAnalytics(Request $request)
    {
        try {
            $timeframe = $request->get('timeframe', 'monthly');
            
            Log::info('Fetching sales analytics', ['timeframe' => $timeframe]);

            $salesData = $this->getSalesChartData($timeframe);
            $productData = $this->getProductAnalytics();

            return response()->json([
                'success' => true,
                'sales_data' => $salesData,
                'product_data' => $productData,
                'timeframe' => $timeframe,
                'message' => 'Analytics data fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching sales analytics: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate sales chart data based on timeframe
     */
    private function getSalesChartData($timeframe)
    {
        $data = [];
        $labels = [];
        $revenueData = [];
        $ordersData = [];

        switch ($timeframe) {
            case 'daily':
                // Last 7 days
                for ($i = 6; $i >= 0; $i--) {
                    $date = Carbon::now()->subDays($i);
                    $labels[] = $date->format('D');
                    
                    $revenue = Order::whereDate('order_date', $date)
                        ->where('payment_status', 'paid')
                        ->sum('total_amount');
                    
                    $orders = Order::whereDate('order_date', $date)->count();
                    
                    $revenueData[] = (float) $revenue;
                    $ordersData[] = $orders;
                }
                break;

            case 'weekly':
                // Last 8 weeks
                for ($i = 7; $i >= 0; $i--) {
                    $startOfWeek = Carbon::now()->subWeeks($i)->startOfWeek();
                    $endOfWeek = Carbon::now()->subWeeks($i)->endOfWeek();
                    
                    $labels[] = 'W' . $startOfWeek->weekOfYear;
                    
                    $revenue = Order::whereBetween('order_date', [$startOfWeek, $endOfWeek])
                        ->where('payment_status', 'paid')
                        ->sum('total_amount');
                    
                    $orders = Order::whereBetween('order_date', [$startOfWeek, $endOfWeek])->count();
                    
                    $revenueData[] = (float) $revenue;
                    $ordersData[] = $orders;
                }
                break;

            case 'monthly':
            default:
                // Last 12 months
                for ($i = 11; $i >= 0; $i--) {
                    $date = Carbon::now()->subMonths($i);
                    $labels[] = $date->format('M');
                    
                    $revenue = Order::whereYear('order_date', $date->year)
                        ->whereMonth('order_date', $date->month)
                        ->where('payment_status', 'paid')
                        ->sum('total_amount');
                    
                    $orders = Order::whereYear('order_date', $date->year)
                        ->whereMonth('order_date', $date->month)
                        ->count();
                    
                    $revenueData[] = (float) $revenue;
                    $ordersData[] = $orders;
                }
                break;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Sales Revenue',
                    'data' => $revenueData,
                    'borderColor' => '#395886',
                    'backgroundColor' => 'rgba(57, 88, 134, 0.1)',
                    'tension' => 0.4,
                    'fill' => true
                ],
                [
                    'label' => 'Number of Orders',
                    'data' => $ordersData,
                    'borderColor' => '#5a8d8a',
                    'backgroundColor' => 'rgba(90, 141, 138, 0.1)',
                    'tension' => 0.4,
                    'fill' => true
                ]
            ]
        ];
    }

    /**
     * Get product analytics (top and least selling products)
     */
    private function getProductAnalytics()
    {
        // Top selling products
        $topProducts = OrderItem::select(
                'medical_supplies.name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.total_price) as total_revenue'),
                DB::raw('COUNT(DISTINCT order_items.orderID) as order_count')
            )
            ->join('medical_supplies', 'order_items.supplyID', '=', 'medical_supplies.supplyID')
            ->join('orders', 'order_items.orderID', '=', 'orders.orderID')
            ->where('orders.payment_status', 'paid')
            ->groupBy('medical_supplies.supplyID', 'medical_supplies.name')
            ->orderBy('total_revenue', 'desc')
            ->limit(5)
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->name,
                    'sales' => (float) $item->total_revenue,
                    'orders' => $item->order_count,
                    'quantity' => $item->total_quantity
                ];
            });

        // Least selling products
        $leastProducts = OrderItem::select(
                'medical_supplies.name',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.total_price) as total_revenue'),
                DB::raw('COUNT(DISTINCT order_items.orderID) as order_count')
            )
            ->join('medical_supplies', 'order_items.supplyID', '=', 'medical_supplies.supplyID')
            ->join('orders', 'order_items.orderID', '=', 'orders.orderID')
            ->where('orders.payment_status', 'paid')
            ->groupBy('medical_supplies.supplyID', 'medical_supplies.name')
            ->orderBy('total_revenue', 'asc')
            ->limit(3)
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->name,
                    'sales' => (float) $item->total_revenue,
                    'orders' => $item->order_count,
                    'quantity' => $item->total_quantity
                ];
            });

        // Chart data for top products
        $chartData = [
            'labels' => $topProducts->pluck('name'),
            'datasets' => [
                [
                    'label' => 'Sales Revenue',
                    'data' => $topProducts->pluck('sales'),
                    'backgroundColor' => [
                        '#395886', '#5a8d8a', '#477977', '#9CA3AF', '#D1D5DB'
                    ]
                ]
            ]
        ];

        return [
            'topProducts' => $topProducts,
            'leastProducts' => $leastProducts,
            'chartData' => $chartData
        ];
    }

    /**
     * Download comprehensive sales report
     */
    public function downloadSalesReport(Request $request)
    {
        try {
            $timeframe = $request->get('timeframe', 'monthly');
            $reportType = $request->get('type', 'sales');

            Log::info('Generating sales report', [
                'timeframe' => $timeframe,
                'type' => $reportType
            ]);

            if ($reportType === 'sales') {
                $data = $this->generateSalesReportData($timeframe);
            } else {
                $data = $this->generateProductReportData();
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Report data generated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating sales report: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    private function generateSalesReportData($timeframe)
    {
        $salesData = $this->getSalesChartData($timeframe);
        
        return [
            'timeframe' => $timeframe,
            'generated_at' => now()->toISOString(),
            'period' => $this->getTimeframeLabel($timeframe),
            'total_revenue' => array_sum($salesData['datasets'][0]['data']),
            'total_orders' => array_sum($salesData['datasets'][1]['data']),
            'data' => $salesData
        ];
    }

    private function generateProductReportData()
    {
        $productData = $this->getProductAnalytics();
        
        return [
            'generated_at' => now()->toISOString(),
            'total_products_analyzed' => count($productData['topProducts']) + count($productData['leastProducts']),
            'top_products_revenue' => array_sum(array_column($productData['topProducts'], 'sales')),
            'data' => $productData
        ];
    }

    private function getTimeframeLabel($timeframe)
    {
        switch ($timeframe) {
            case 'daily':
                return 'Last 7 Days';
            case 'weekly':
                return 'Last 8 Weeks';
            case 'monthly':
                return 'Last 12 Months';
            default:
                return 'Monthly';
        }
    }

    /**
     * Search orders by various criteria
     */
    public function searchOrders(Request $request)
    {
        try {
            $searchTerm = $request->get('q', '');
            $limit = $request->get('limit', 10);

            if (empty($searchTerm)) {
                return response()->json([
                    'success' => true,
                    'orders' => [],
                    'message' => 'Please provide a search term'
                ]);
            }

            $orders = Order::with(['patient.user', 'orderItems.medicalSupply'])
                ->where(function($query) use ($searchTerm) {
                    $query->where('orderID', 'like', "%{$searchTerm}%")
                          ->orWhereHas('patient.user', function($q) use ($searchTerm) {
                              $q->where('first_name', 'like', "%{$searchTerm}%")
                                ->orWhere('last_name', 'like', "%{$searchTerm}%")
                                ->orWhere('email', 'like', "%{$searchTerm}%");
                          })
                          ->orWhereHas('patient', function($q) use ($searchTerm) {
                              $q->where('hospitalNumber', 'like', "%{$searchTerm}%");
                          })
                          ->orWhere('payment_reference', 'like', "%{$searchTerm}%");
                })
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($order) {
                    return $this->transformOrder($order);
                });

            return response()->json([
                'success' => true,
                'orders' => $orders,
                'message' => 'Search completed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching orders: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to search orders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transform order data for response
     */
    private function transformOrder($order, $detailed = false)
    {
        try {
            $orderReference = 'ORD-' . $order->created_at->format('YmdHis') . '-' . str_pad($order->orderID, 6, '0', STR_PAD_LEFT);

            $baseData = [
                'id' => $order->orderID,
                'order_reference' => $orderReference,
                'patient' => [
                    'id' => $order->patient->patientID ?? null,
                    'hospital_number' => $order->patient->hospitalNumber ?? 'N/A',
                    'name' => ($order->patient->user->first_name ?? '') . ' ' . ($order->patient->user->last_name ?? ''),
                    'email' => $order->patient->user->email ?? 'N/A'
                ],
                'total_amount' => (float) ($order->total_amount ?? 0),
                'subtotal' => (float) ($order->subtotal ?? 0),
                'discount_percentage' => (float) ($order->discount_percentage ?? 0),
                'discount_amount' => (float) ($order->discount_amount ?? 0),
                'payment_method' => $order->payment_method ?? 'unknown',
                'payment_status' => $order->payment_status ?? 'pending',
                'order_status' => $order->order_status ?? 'pending',
                'payment_reference' => $order->payment_reference ?? null,
                'order_date' => $order->order_date?->toISOString(),
                'scheduled_pickup_date' => $order->scheduled_pickup_date,
                'created_at' => $order->created_at?->toISOString(),
                'updated_at' => $order->updated_at?->toISOString(),
                'items_count' => $order->orderItems->count(),
                'items' => $order->orderItems->map(function($item) {
                    return [
                        'id' => $item->order_itemID,
                        'supply_name' => $item->medicalSupply->name ?? 'Unknown Product',
                        'category' => $item->medicalSupply->category ?? 'General',
                        'quantity' => $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'total_price' => (float) $item->total_price,
                        'image' => $item->medicalSupply->image ?? null,
                        'image_url' => $item->medicalSupply->image ? 
                            asset('assets/images/Medical supplies/' . $item->medicalSupply->image) : null
                    ];
                })
            ];

            if ($detailed && $order->payment) {
                $baseData['payment'] = [
                    'id' => $order->payment->paymentID,
                    'amount' => (float) $order->payment->amount,
                    'payment_method' => $order->payment->payment_method,
                    'status' => $order->payment->status,
                    'payment_reference' => $order->payment->payment_reference,
                    'payment_date' => $order->payment->payment_date?->toISOString()
                ];
            }

            return $baseData;
        } catch (\Exception $e) {
            Log::error('Error transforming order: ' . $e->getMessage());
            return [
                'id' => $order->orderID,
                'order_reference' => 'ORD-' . $order->orderID,
                'patient' => ['name' => 'Unknown', 'hospital_number' => 'N/A', 'email' => 'N/A'],
                'total_amount' => 0,
                'order_status' => $order->order_status,
                'items_count' => 0,
                'items' => []
            ];
        }
    }

    /**
     * Get order metrics for quick overview
     */
    public function getOrderMetrics()
    {
        try {
            $today = Carbon::today();
            
            $metrics = [
                'today' => [
                    'orders' => Order::whereDate('order_date', $today)->count(),
                    'revenue' => (float) Order::whereDate('order_date', $today)
                        ->where('payment_status', 'paid')
                        ->sum('total_amount'),
                    'average_order_value' => (float) Order::whereDate('order_date', $today)
                        ->where('payment_status', 'paid')
                        ->avg('total_amount')
                ],
                'yesterday' => [
                    'orders' => Order::whereDate('order_date', $today->copy()->subDay())->count(),
                    'revenue' => (float) Order::whereDate('order_date', $today->copy()->subDay())
                        ->where('payment_status', 'paid')
                        ->sum('total_amount')
                ],
                'this_week' => [
                    'orders' => Order::whereBetween('order_date', [
                        $today->copy()->startOfWeek(), 
                        $today->copy()->endOfWeek()
                    ])->count(),
                    'revenue' => (float) Order::whereBetween('order_date', [
                        $today->copy()->startOfWeek(), 
                        $today->copy()->endOfWeek()
                    ])->where('payment_status', 'paid')->sum('total_amount')
                ],
                'this_month' => [
                    'orders' => Order::whereBetween('order_date', [
                        $today->copy()->startOfMonth(), 
                        $today->copy()->endOfMonth()
                    ])->count(),
                    'revenue' => (float) Order::whereBetween('order_date', [
                        $today->copy()->startOfMonth(), 
                        $today->copy()->endOfMonth()
                    ])->where('payment_status', 'paid')->sum('total_amount')
                ]
            ];

            return response()->json([
                'success' => true,
                'metrics' => $metrics,
                'message' => 'Order metrics fetched successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching order metrics: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order metrics: ' . $e->getMessage()
            ], 500);
        }
    }
}