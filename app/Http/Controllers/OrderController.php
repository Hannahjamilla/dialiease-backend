<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\MedicalSupply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Get all orders for the authenticated patient
     */
    public function getPatientOrders(Request $request)
    {
        try {
            Log::info('getPatientOrders method called');
            Log::info('Auth user:', ['user' => Auth::user()]);
            
            $user = Auth::user();
            
            if (!$user) {
                Log::warning('No authenticated user found');
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized - Please log in'
                ], 401);
            }

            Log::info('User details:', [
                'userID' => $user->userID,
                'patientID' => $user->patientID ?? 'No patientID',
                'type' => $user->type
            ]);

            // Determine the patientID to use
            $patientID = $this->getPatientID($user);
            
            if (!$patientID) {
                Log::warning('Could not determine patientID for user', ['userID' => $user->userID]);
                return response()->json([
                    'success' => false,
                    'message' => 'Could not find patient information'
                ], 404);
            }

            Log::info('Using patientID: ' . $patientID . ' for userID: ' . $user->userID);

            // Get all orders for this patient with related data
            $orders = Order::where('patientID', $patientID)
                ->with(['orderItems.supply', 'payment']) // Changed 'items' to 'orderItems'
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('Raw database query result count: ' . $orders->count());

            if ($orders->count() === 0) {
                Log::info('No orders found for patientID: ' . $patientID);
                Log::info('Checking if patientID exists in orders table...');
                
                // Debug: Check what patientIDs exist in orders
                $existingPatientIDs = Order::select('patientID')
                    ->distinct()
                    ->pluck('patientID')
                    ->toArray();
                Log::info('Existing patientIDs in orders table: ', $existingPatientIDs);
            }

            // Format the orders data according to your database structure
            $formattedOrders = $orders->map(function ($order) {
                return [
                    'orderID' => $order->orderID,
                    'userID' => $order->userID,
                    'patientID' => $order->patientID,
                    'order_date' => $order->order_date?->toISOString() ?? $order->created_at?->toISOString(),
                    'order_status' => $order->order_status ?? 'pending',
                    'total_amount' => (float) $order->total_amount,
                    'subtotal' => (float) $order->subtotal,
                    'discount_amount' => (float) ($order->discount_amount ?? 0),
                    'discount_percentage' => (float) ($order->discount_percentage ?? 0),
                    'scheduled_pickup_date' => $order->scheduled_pickup_date?->toISOString(),
                    'items' => $order->orderItems->map(function ($item) { // Changed to orderItems
                        return [
                            'order_itemID' => $item->order_itemID,
                            'supplyID' => $item->supplyID,
                            'product_name' => $item->supply ? $item->supply->name : 'Medical Supply',
                            'quantity' => (int) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'total_price' => (float) $item->total_price
                        ];
                    }),
                    'payment_method' => $order->payment_method ?? ($order->payment ? $order->payment->payment_method : 'Not specified'),
                    'payment_status' => $order->payment_status ?? ($order->payment ? $order->payment->status : 'pending'),
                    'payment_reference' => $order->payment_reference ?? ($order->payment ? $order->payment->payment_reference : null),
                    'created_at' => $order->created_at?->toISOString(),
                    'updated_at' => $order->updated_at?->toISOString()
                ];
            });

            return response()->json([
                'success' => true,
                'orders' => $formattedOrders,
                'count' => $formattedOrders->count(),
                'debug_info' => [
                    'user_patientID' => $user->patientID,
                    'used_patientID' => $patientID,
                    'userID' => $user->userID,
                    'orders_found' => $orders->count(),
                    'query_condition' => "patientID = $patientID"
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getPatientOrders: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Determine the patientID for the authenticated user
     */
    private function getPatientID($user)
    {
        // First priority: Use patientID from user if available
        if (!empty($user->patientID) && $user->patientID !== 'No patientID') {
            Log::info('Using patientID from user: ' . $user->patientID);
            return $user->patientID;
        }

        // Second priority: Check if userID exists as patientID in orders table
        $orderWithUserID = Order::where('patientID', $user->userID)->first();
        if ($orderWithUserID) {
            Log::info('Found orders with patientID = userID: ' . $user->userID);
            return $user->userID;
        }

        // Third priority: Check if there are any orders for this userID in userID column
        $orderForUser = Order::where('userID', $user->userID)->first();
        if ($orderForUser) {
            Log::info('Found orders with userID matching: ' . $user->userID . ', using patientID: ' . $orderForUser->patientID);
            return $orderForUser->patientID;
        }

        // Fourth priority: Try to find the most common patientID for this user
        $commonPatientID = Order::where('userID', $user->userID)
            ->select('patientID', DB::raw('COUNT(*) as order_count'))
            ->groupBy('patientID')
            ->orderBy('order_count', 'desc')
            ->first();

        if ($commonPatientID) {
            Log::info('Using most common patientID for user: ' . $commonPatientID->patientID);
            return $commonPatientID->patientID;
        }

        // Last resort: Use userID as patientID
        Log::info('Using userID as patientID: ' . $user->userID);
        return $user->userID;
    }

    /**
     * Get detailed order information
     */
    public function getOrderDetails($orderID)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $patientID = $this->getPatientID($user);
            
            if (!$patientID) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not find patient information'
                ], 404);
            }

            Log::info('Getting order details for orderID: ' . $orderID . ', patientID: ' . $patientID);

            // Get the specific order for this patient
            $order = Order::where('orderID', $orderID)
                ->where('patientID', $patientID)
                ->with(['orderItems.supply', 'payment']) // Changed 'items' to 'orderItems'
                ->first();

            if (!$order) {
                Log::warning('Order not found or access denied', [
                    'orderID' => $orderID,
                    'patientID' => $patientID,
                    'userID' => $user->userID
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found or you do not have permission to view this order'
                ], 404);
            }

            // Format order details according to your database structure
            $formattedOrder = [
                'orderID' => $order->orderID,
                'userID' => $order->userID,
                'patientID' => $order->patientID,
                'order_date' => $order->order_date?->toISOString() ?? $order->created_at?->toISOString(),
                'order_status' => $order->order_status ?? 'pending',
                'total_amount' => (float) $order->total_amount,
                'subtotal' => (float) $order->subtotal,
                'discount_amount' => (float) ($order->discount_amount ?? 0),
                'discount_percentage' => (float) ($order->discount_percentage ?? 0),
                'scheduled_pickup_date' => $order->scheduled_pickup_date?->toISOString(),
                'items' => $order->orderItems->map(function ($item) { // Changed to orderItems
                    return [
                        'order_itemID' => $item->order_itemID,
                        'supplyID' => $item->supplyID,
                        'product_name' => $item->supply ? $item->supply->name : 'Medical Supply',
                        'quantity' => (int) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'total_price' => (float) $item->total_price,
                        'supply_details' => $item->supply
                    ];
                }),
                'payment_method' => $order->payment_method ?? ($order->payment ? $order->payment->payment_method : 'Not specified'),
                'payment_status' => $order->payment_status ?? ($order->payment ? $order->payment->status : 'pending'),
                'payment_reference' => $order->payment_reference ?? ($order->payment ? $order->payment->payment_reference : null),
                'payment_date' => $order->payment ? $order->payment->payment_date : null,
                'created_at' => $order->created_at?->toISOString(),
                'updated_at' => $order->updated_at?->toISOString()
            ];

            return response()->json([
                'success' => true,
                'order' => $formattedOrder
            ]);

        } catch (\Exception $e) {
            Log::error('Error in getOrderDetails: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel an order
     */
    public function cancelOrder($orderID, Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $patientID = $this->getPatientID($user);
            
            if (!$patientID) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not find patient information'
                ], 404);
            }

            $order = Order::where('orderID', $orderID)
                ->where('patientID', $patientID)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Check if order can be cancelled
            if (!in_array($order->order_status, ['pending', 'processing', 'confirmed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order cannot be cancelled at this stage'
                ], 400);
            }

            // Start transaction
            DB::beginTransaction();

            try {
                // Update order status
                $order->order_status = 'cancelled';
                $order->save();

                // Update payment status if payment exists
                if ($order->payment) {
                    $order->payment->status = 'cancelled';
                    $order->payment->save();
                }

                // Restore stock for each item
                foreach ($order->orderItems as $item) { // Changed to orderItems
                    if ($item->supply) {
                        $item->supply->stock += $item->quantity;
                        $item->supply->save();
                    }
                }

                DB::commit();

                Log::info('Order cancelled successfully', ['orderID' => $orderID, 'patientID' => $patientID]);

                return response()->json([
                    'success' => true,
                    'message' => 'Order cancelled successfully',
                    'order' => [
                        'orderID' => $order->orderID,
                        'order_status' => $order->order_status
                    ]
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error in cancelOrder: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * DIRECT DEBUG ENDPOINT - Get orders with raw SQL for debugging
     */
    public function debugOrders(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            Log::info('DEBUG: User details', ['user' => $user->toArray()]);

            // Raw SQL to see what's happening
            $rawOrders = DB::select("
                SELECT 
                    o.orderID,
                    o.userID,
                    o.patientID,
                    o.order_status,
                    o.total_amount,
                    COUNT(oi.order_itemID) as item_count
                FROM orders o
                LEFT JOIN order_items oi ON o.orderID = oi.orderID
                WHERE o.patientID = ? OR o.userID = ?
                GROUP BY o.orderID, o.userID, o.patientID, o.order_status, o.total_amount
                ORDER BY o.created_at DESC
            ", [$user->userID, $user->userID]);

            Log::info('DEBUG: Raw SQL results', ['orders' => $rawOrders]);

            return response()->json([
                'success' => true,
                'debug_info' => [
                    'user' => [
                        'userID' => $user->userID,
                        'patientID' => $user->patientID,
                        'type' => $user->type
                    ],
                    'raw_orders' => $rawOrders,
                    'sql_query' => "WHERE patientID = {$user->userID} OR userID = {$user->userID}"
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error in debugOrders: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Debug failed: ' . $e->getMessage()
            ], 500);
        }
    }
}