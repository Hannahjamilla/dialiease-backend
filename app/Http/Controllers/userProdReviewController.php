<?php

namespace App\Http\Controllers;

use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserProdReviewController extends Controller
{
    /**
     * Get product reviews for management - FIXED VERSION
     */
    public function getProductReviews(Request $request)
    {
        try {
            Log::info('=== STARTING getProductReviews ===');
            Log::info('Request parameters:', $request->all());

            // First, let's check what columns exist in the users table
            $userColumns = DB::getSchemaBuilder()->getColumnListing('users');
            Log::info('Users table columns:', $userColumns);

            $supplyColumns = DB::getSchemaBuilder()->getColumnListing('medical_supplies');
            Log::info('Medical supplies table columns:', $supplyColumns);

            // Build the base query with safe column names
            $query = DB::table('product_reviews as pr')
                ->leftJoin('users as u', 'pr.userID', '=', 'u.userID')
                ->leftJoin('medical_supplies as ms', 'pr.supplyID', '=', 'ms.supplyID')
                ->select(
                    'pr.reviewID',
                    'pr.userID',
                    'pr.supplyID',
                    'pr.rating',
                    'pr.comment',
                    'pr.status',
                    'pr.is_anonymous',
                    'pr.created_at',
                    'pr.updated_at'
                );

            // Add user columns safely - check what exists
            if (in_array('name', $userColumns)) {
                $query->addSelect('u.name as user_name');
            } else if (in_array('username', $userColumns)) {
                $query->addSelect('u.username as user_name');
            } else if (in_array('first_name', $userColumns) && in_array('last_name', $userColumns)) {
                $query->addSelect(DB::raw("CONCAT(u.first_name, ' ', u.last_name) as user_name"));
            } else {
                $query->addSelect(DB::raw("'Unknown User' as user_name"));
            }

            // Add email if exists
            if (in_array('email', $userColumns)) {
                $query->addSelect('u.email as user_email');
            } else {
                $query->addSelect(DB::raw("'No Email' as user_email"));
            }

            // Add supply name if exists
            if (in_array('name', $supplyColumns)) {
                $query->addSelect('ms.name as supply_name');
            } else if (in_array('product_name', $supplyColumns)) {
                $query->addSelect('ms.product_name as supply_name');
            } else {
                $query->addSelect(DB::raw("'Unknown Product' as supply_name"));
            }

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search, $userColumns, $supplyColumns) {
                    $q->where('pr.comment', 'like', "%{$search}%");
                    
                    // User search
                    if (in_array('name', $userColumns)) {
                        $q->orWhere('u.name', 'like', "%{$search}%");
                    } else if (in_array('username', $userColumns)) {
                        $q->orWhere('u.username', 'like', "%{$search}%");
                    } else if (in_array('first_name', $userColumns) && in_array('last_name', $userColumns)) {
                        $q->orWhere('u.first_name', 'like', "%{$search}%")
                          ->orWhere('u.last_name', 'like', "%{$search}%");
                    }
                    
                    // Email search
                    if (in_array('email', $userColumns)) {
                        $q->orWhere('u.email', 'like', "%{$search}%");
                    }
                    
                    // Supply search
                    if (in_array('name', $supplyColumns)) {
                        $q->orWhere('ms.name', 'like', "%{$search}%");
                    } else if (in_array('product_name', $supplyColumns)) {
                        $q->orWhere('ms.product_name', 'like', "%{$search}%");
                    }
                });
            }

            // Filter by status
            if ($request->has('status') && !empty($request->status)) {
                $query->where('pr.status', $request->status);
            }

            // Filter by rating
            if ($request->has('rating') && !empty($request->rating)) {
                $query->where('pr.rating', $request->rating);
            }

            // Sort by
            $sortBy = $request->get('sort_by', 'pr.created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $request->get('per_page', 10);
            
            Log::info('Executing query...');
            $reviews = $query->paginate($perPage);
            Log::info('Query executed successfully. Found: ' . $reviews->total() . ' reviews');

            // Transform the data
            $transformedReviews = $reviews->getCollection()->map(function($review) {
                return [
                    'reviewID' => $review->reviewID,
                    'userID' => $review->userID,
                    'supplyID' => $review->supplyID,
                    'rating' => (int) $review->rating,
                    'comment' => $review->comment,
                    'status' => $review->status,
                    'is_anonymous' => (bool) $review->is_anonymous,
                    'created_at' => $review->created_at,
                    'updated_at' => $review->updated_at,
                    'user' => [
                        'userID' => $review->userID,
                        'name' => $review->user_name ?? 'Unknown User',
                        'email' => $review->user_email ?? 'No Email'
                    ],
                    'supply' => [
                        'supplyID' => $review->supplyID,
                        'name' => $review->supply_name ?? 'Unknown Product'
                    ]
                ];
            });

            Log::info('=== ENDING getProductReviews - SUCCESS ===');

            return response()->json([
                'success' => true,
                'reviews' => $transformedReviews,
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'total_pages' => $reviews->lastPage(),
                    'total_items' => $reviews->total(),
                    'per_page' => $reviews->perPage(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('=== ERROR in getProductReviews ===');
            Log::error('Error message: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            Log::error('File: ' . $e->getFile());
            Log::error('Line: ' . $e->getLine());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product reviews',
                'error' => $e->getMessage(),
                'trace' => env('APP_DEBUG') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Get review statistics
     */
    public function getReviewStatistics()
    {
        try {
            Log::info('Fetching review statistics...');

            $totalReviews = DB::table('product_reviews')->count();
            $pendingReviews = DB::table('product_reviews')->where('status', 'pending')->count();
            $approvedReviews = DB::table('product_reviews')->where('status', 'approved')->count();
            $rejectedReviews = DB::table('product_reviews')->where('status', 'rejected')->count();

            $ratingDistribution = DB::table('product_reviews')
                ->select('rating', DB::raw('COUNT(*) as count'))
                ->groupBy('rating')
                ->orderBy('rating', 'desc')
                ->get();

            Log::info('Statistics fetched successfully');

            return response()->json([
                'success' => true,
                'statistics' => [
                    'total_reviews' => $totalReviews,
                    'pending_reviews' => $pendingReviews,
                    'approved_reviews' => $approvedReviews,
                    'rejected_reviews' => $rejectedReviews,
                    'rating_distribution' => $ratingDistribution
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching review statistics: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch review statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update review status
     */
    public function updateReviewStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,approved,rejected'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $review = DB::table('product_reviews')->where('reviewID', $id)->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product review not found'
                ], 404);
            }

            DB::table('product_reviews')
                ->where('reviewID', $id)
                ->update([
                    'status' => $request->status,
                    'updated_at' => now()
                ]);

            // Get the updated review with the same safe query structure
            $userColumns = DB::getSchemaBuilder()->getColumnListing('users');
            $supplyColumns = DB::getSchemaBuilder()->getColumnListing('medical_supplies');

            $updatedQuery = DB::table('product_reviews as pr')
                ->leftJoin('users as u', 'pr.userID', '=', 'u.userID')
                ->leftJoin('medical_supplies as ms', 'pr.supplyID', '=', 'ms.supplyID')
                ->select(
                    'pr.reviewID',
                    'pr.userID',
                    'pr.supplyID',
                    'pr.rating',
                    'pr.comment',
                    'pr.status',
                    'pr.is_anonymous',
                    'pr.created_at',
                    'pr.updated_at'
                );

            // Add user columns safely
            if (in_array('name', $userColumns)) {
                $updatedQuery->addSelect('u.name as user_name');
            } else if (in_array('username', $userColumns)) {
                $updatedQuery->addSelect('u.username as user_name');
            } else if (in_array('first_name', $userColumns) && in_array('last_name', $userColumns)) {
                $updatedQuery->addSelect(DB::raw("CONCAT(u.first_name, ' ', u.last_name) as user_name"));
            } else {
                $updatedQuery->addSelect(DB::raw("'Unknown User' as user_name"));
            }

            if (in_array('email', $userColumns)) {
                $updatedQuery->addSelect('u.email as user_email');
            } else {
                $updatedQuery->addSelect(DB::raw("'No Email' as user_email"));
            }

            if (in_array('name', $supplyColumns)) {
                $updatedQuery->addSelect('ms.name as supply_name');
            } else if (in_array('product_name', $supplyColumns)) {
                $updatedQuery->addSelect('ms.product_name as supply_name');
            } else {
                $updatedQuery->addSelect(DB::raw("'Unknown Product' as supply_name"));
            }

            $updatedReview = $updatedQuery->where('pr.reviewID', $id)->first();

            $formattedReview = [
                'reviewID' => $updatedReview->reviewID,
                'userID' => $updatedReview->userID,
                'supplyID' => $updatedReview->supplyID,
                'rating' => (int) $updatedReview->rating,
                'comment' => $updatedReview->comment,
                'status' => $updatedReview->status,
                'is_anonymous' => (bool) $updatedReview->is_anonymous,
                'created_at' => $updatedReview->created_at,
                'updated_at' => $updatedReview->updated_at,
                'user' => [
                    'userID' => $updatedReview->userID,
                    'name' => $updatedReview->user_name ?? 'Unknown User',
                    'email' => $updatedReview->user_email ?? 'No Email'
                ],
                'supply' => [
                    'supplyID' => $updatedReview->supplyID,
                    'name' => $updatedReview->supply_name ?? 'Unknown Product'
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Review status updated successfully',
                'review' => $formattedReview
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating review status: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review status'
            ], 500);
        }
    }

    /**
     * Delete product review
     */
    public function deleteProductReview($id)
    {
        try {
            $review = DB::table('product_reviews')->where('reviewID', $id)->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product review not found'
                ], 404);
            }

            DB::table('product_reviews')->where('reviewID', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product review deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting product review: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product review'
            ], 500);
        }
    }
}