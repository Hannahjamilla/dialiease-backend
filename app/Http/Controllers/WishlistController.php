<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use App\Models\MedicalSupply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WishlistController extends Controller
{
    /**
     * Get user's wishlist
     */
    public function getUserWishlist()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $wishlist = Wishlist::with(['medicalSupply'])
                ->where('userID', $user->userID)
                ->get()
                ->map(function($item) {
                    $supply = $item->medicalSupply;
                    if (!$supply) return null;

                    return [
                        'wishlistID' => $item->wishlistID,
                        'supplyID' => $supply->supplyID,
                        'name' => $supply->name,
                        'category' => $supply->category,
                        'price' => (float) $supply->price,
                        'stock' => (int) $supply->stock,
                        'image' => $supply->image,
                        'imageUrl' => $supply->image ? asset('assets/images/Medical supplies/' . $supply->image) : null,
                        'added_at' => $item->added_at->toISOString()
                    ];
                })
                ->filter()
                ->values();

            return response()->json([
                'success' => true,
                'wishlist' => $wishlist
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching wishlist: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch wishlist'
            ], 500);
        }
    }

    /**
     * Add item to wishlist
     */
    public function addToWishlist(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $request->validate([
                'supplyID' => 'required|exists:medical_supplies,supplyID'
            ]);

            $existing = Wishlist::where('userID', $user->userID)
                ->where('supplyID', $request->supplyID)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item already in wishlist'
                ], 400);
            }

            $wishlist = Wishlist::create([
                'userID' => $user->userID,
                'supplyID' => $request->supplyID
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Item added to wishlist',
                'wishlistItem' => $wishlist
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding to wishlist: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add item to wishlist'
            ], 500);
        }
    }

    /**
     * Remove item from wishlist
     */
    public function removeFromWishlist($supplyID)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $deleted = Wishlist::where('userID', $user->userID)
                ->where('supplyID', $supplyID)
                ->delete();

            if ($deleted) {
                return response()->json([
                    'success' => true,
                    'message' => 'Item removed from wishlist'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Item not found in wishlist'
            ], 404);

        } catch (\Exception $e) {
            Log::error('Error removing from wishlist: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove item from wishlist'
            ], 500);
        }
    }
}