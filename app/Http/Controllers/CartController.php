<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\MedicalSupply;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Get user's cart items
     */
    public function getUserCart(Request $request)
    {
        try {
            Log::info('Fetching user cart', ['user_id' => Auth::id()]);
            
            $user = Auth::user();
            
            if (!$user) {
                Log::warning('User not authenticated when fetching cart');
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            Log::info('User authenticated', ['user_id' => $user->userID, 'email' => $user->email]);

            $cartItems = Cart::with(['medicalSupply'])
                ->where('userID', $user->userID)
                ->get()
                ->map(function($cartItem) {
                    if (!$cartItem->medicalSupply) {
                        Log::warning('Medical supply not found for cart item', ['cart_id' => $cartItem->cartID, 'supply_id' => $cartItem->supplyID]);
                        return null;
                    }
                    
                    $supply = $cartItem->medicalSupply;
                    return [
                        'cartID' => $cartItem->cartID,
                        'supplyID' => $supply->supplyID,
                        'name' => $supply->name,
                        'category' => $supply->category,
                        'description' => $supply->description,
                        'stock' => (int) $supply->stock,
                        'price' => (float) $supply->price,
                        'quantity' => $cartItem->quantity,
                        'image' => $supply->image,
                        'imageUrl' => $supply->image ? asset('assets/images/Medical supplies/' . $supply->image) : null,
                        'added_at' => $cartItem->added_at ? $cartItem->added_at->toISOString() : $cartItem->created_at?->toISOString()
                    ];
                })
                ->filter()
                ->values();

            Log::info('Cart items fetched successfully', [
                'user_id' => $user->userID,
                'cart_count' => $cartItems->count()
            ]);

            $totalPrice = $cartItems->sum(function($item) {
                return $item['price'] * $item['quantity'];
            });

            return response()->json([
                'success' => true,
                'cart' => $cartItems,
                'totalItems' => $cartItems->sum('quantity'),
                'totalPrice' => $totalPrice
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user cart: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cart items: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add item to cart
     */
    public function addToCart(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'supplyID' => 'required|exists:medical_supplies,supplyID',
                'quantity' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $supply = MedicalSupply::where('supplyID', $request->supplyID)
                ->where('status', 'active')
                ->first();

            if (!$supply) {
                return response()->json([
                    'success' => false,
                    'message' => 'Medical supply not found or not available'
                ], 404);
            }
            
            if ($supply->stock < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough stock available. Only ' . $supply->stock . ' items left.'
                ], 400);
            }

            // Check if item already in cart
            $existingCartItem = Cart::where('userID', $user->userID)
                ->where('supplyID', $request->supplyID)
                ->first();

            if ($existingCartItem) {
                $newQuantity = $existingCartItem->quantity + $request->quantity;
                
                if ($supply->stock < $newQuantity) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Not enough stock available for additional quantity. Maximum available: ' . $supply->stock
                    ], 400);
                }

                $existingCartItem->update(['quantity' => $newQuantity]);
                $cartItem = $existingCartItem;
                
                Log::info('Cart item quantity updated', [
                    'user_id' => $user->userID,
                    'supply_id' => $request->supplyID,
                    'new_quantity' => $newQuantity
                ]);
            } else {
                $cartItem = Cart::create([
                    'userID' => $user->userID,
                    'supplyID' => $request->supplyID,
                    'quantity' => $request->quantity
                ]);
                
                Log::info('New cart item added', [
                    'user_id' => $user->userID,
                    'supply_id' => $request->supplyID,
                    'quantity' => $request->quantity
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Item added to cart successfully',
                'cartItem' => [
                    'cartID' => $cartItem->cartID,
                    'supplyID' => $supply->supplyID,
                    'name' => $supply->name,
                    'price' => (float) $supply->price,
                    'quantity' => $cartItem->quantity,
                    'stock' => (int) $supply->stock,
                    'imageUrl' => $supply->image ? asset('assets/images/Medical supplies/' . $supply->image) : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding item to cart: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add item to cart: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update cart item quantity
     */
    public function updateCartItem(Request $request, $cartID)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'quantity' => 'required|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $cartItem = Cart::where('cartID', $cartID)
                ->where('userID', $user->userID)
                ->first();

            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
            }

            if ($request->quantity == 0) {
                $cartItem->delete();
                
                Log::info('Cart item removed', [
                    'user_id' => $user->userID,
                    'cart_id' => $cartID
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Item removed from cart'
                ]);
            }

            $supply = $cartItem->medicalSupply;
            
            if (!$supply) {
                return response()->json([
                    'success' => false,
                    'message' => 'Medical supply not found'
                ], 404);
            }
            
            if ($supply->stock < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough stock available. Only ' . $supply->stock . ' items left.'
                ], 400);
            }

            $cartItem->update(['quantity' => $request->quantity]);

            Log::info('Cart item quantity updated', [
                'user_id' => $user->userID,
                'cart_id' => $cartID,
                'new_quantity' => $request->quantity
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cart updated successfully',
                'cartItem' => [
                    'cartID' => $cartItem->cartID,
                    'quantity' => $cartItem->quantity,
                    'price' => (float) $supply->price,
                    'stock' => (int) $supply->stock
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating cart item: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'cart_id' => $cartID
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cart item: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart($cartID)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $cartItem = Cart::where('cartID', $cartID)
                ->where('userID', $user->userID)
                ->first();

            if (!$cartItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found'
                ], 404);
            }

            $cartItem->delete();

            Log::info('Cart item removed', [
                'user_id' => $user->userID,
                'cart_id' => $cartID
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Item removed from cart successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error removing item from cart: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
                'cart_id' => $cartID
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item from cart: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear user's cart
     */
    public function clearCart()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $deletedCount = Cart::where('userID', $user->userID)->delete();

            Log::info('Cart cleared', [
                'user_id' => $user->userID,
                'deleted_count' => $deletedCount
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cart cleared successfully',
                'deleted_count' => $deletedCount
            ]);

        } catch (\Exception $e) {
            Log::error('Error clearing cart: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cart: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cart summary (total items and total price)
     */
    public function getCartSummary(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $cartItems = Cart::with(['medicalSupply'])
                ->where('userID', $user->userID)
                ->get();

            $totalItems = $cartItems->sum('quantity');
            $totalPrice = $cartItems->sum(function($item) {
                return $item->medicalSupply ? $item->medicalSupply->price * $item->quantity : 0;
            });

            return response()->json([
                'success' => true,
                'summary' => [
                    'totalItems' => $totalItems,
                    'totalPrice' => $totalPrice,
                    'itemCount' => $cartItems->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching cart summary: ' . $e->getMessage(), [
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cart summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cart items count (lightweight endpoint for badges)
     */
    public function getCartCount(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $totalItems = Cart::where('userID', $user->userID)->sum('quantity');
            $uniqueItems = Cart::where('userID', $user->userID)->count();

            return response()->json([
                'success' => true,
                'totalItems' => $totalItems,
                'uniqueItems' => $uniqueItems
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching cart count: ' . $e->getMessage(), [
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch cart count: ' . $e->getMessage()
            ], 500);
        }
    }
}