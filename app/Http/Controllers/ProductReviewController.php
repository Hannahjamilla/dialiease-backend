<?php

namespace App\Http\Controllers;

use App\Models\ProductReview;
use App\Models\MedicalSupply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductReviewController extends Controller
{
    /**
     * Get reviews for a product
     */
    public function getProductReviews($supplyID)
    {
        try {
            Log::info('Fetching reviews for product: ' . $supplyID);
            
            // Check if product exists
            $product = MedicalSupply::find($supplyID);
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            $reviews = ProductReview::with(['user'])
                ->where('supplyID', $supplyID)
                ->where('status', 'approved') // Only show approved reviews
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($review) {
                    // Handle anonymous reviews
                    if ($review->is_anonymous) {
                        $userName = 'Anonymous User';
                    } else {
                        $userName = $review->user->name ?? 'Anonymous User';
                    }
                    
                    return [
                        'reviewID' => $review->reviewID,
                        'userName' => $userName,
                        'rating' => (int) $review->rating,
                        'comment' => $review->comment,
                        'isAnonymous' => (bool) $review->is_anonymous,
                        'created_at' => $review->created_at->toISOString(),
                        'time_ago' => $review->created_at->diffForHumans()
                    ];
                });

            // Calculate average rating
            $averageRating = ProductReview::where('supplyID', $supplyID)
                ->where('status', 'approved')
                ->avg('rating') ?? 0;

            $totalReviews = $reviews->count();

            // Calculate rating distribution
            $ratingDistribution = [0, 0, 0, 0, 0]; // [1-star, 2-star, 3-star, 4-star, 5-star]
            
            ProductReview::where('supplyID', $supplyID)
                ->where('status', 'approved')
                ->select('rating')
                ->get()
                ->each(function($review) use (&$ratingDistribution) {
                    if ($review->rating >= 1 && $review->rating <= 5) {
                        $ratingDistribution[$review->rating - 1]++;
                    }
                });

            return response()->json([
                'success' => true,
                'reviews' => $reviews,
                'averageRating' => round($averageRating, 1),
                'totalReviews' => $totalReviews,
                'ratingDistribution' => $ratingDistribution
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching product reviews: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reviews',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit a review
     */
    public function submitReview(Request $request)
    {
        try {
            Log::info('Submit review request received', $request->all());
            
            $user = Auth::user();
            
            if (!$user) {
                Log::warning('User not authenticated for review submission');
                return response()->json([
                    'success' => false,
                    'message' => 'Please login to submit a review'
                ], 401);
            }

            // Manual validation with better error messages
            $validator = Validator::make($request->all(), [
                'supplyID' => 'required|exists:medical_supplies,supplyID',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
                'isAnonymous' => 'boolean'
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed: ', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Please check your input',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if product exists
            $product = MedicalSupply::find($request->supplyID);
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Check if user already reviewed this product
            $existingReview = ProductReview::where('userID', $user->userID)
                ->where('supplyID', $request->supplyID)
                ->first();

            if ($existingReview) {
                Log::warning('User already reviewed this product', [
                    'userID' => $user->userID,
                    'supplyID' => $request->supplyID
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'You have already reviewed this product'
                ], 409);
            }

            // Create the review
            $reviewData = [
                'userID' => $user->userID,
                'supplyID' => $request->supplyID,
                'rating' => $request->rating,
                'comment' => $request->comment ?? '',
                'is_anonymous' => $request->isAnonymous ?? false,
                'status' => 'approved' // or 'pending' if you want to moderate reviews
            ];

            Log::info('Creating review with data: ', $reviewData);

            $review = ProductReview::create($reviewData);

            Log::info('Review created successfully', ['reviewID' => $review->reviewID]);

            return response()->json([
                'success' => true,
                'message' => 'Review submitted successfully!',
                'review' => [
                    'reviewID' => $review->reviewID,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'isAnonymous' => $review->is_anonymous
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error submitting review: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit review. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authenticated user's reviews
     */
    public function getMyReviews()
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please login to view your reviews'
                ], 401);
            }

            $reviews = ProductReview::with(['product'])
                ->where('userID', $user->userID)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($review) {
                    return [
                        'reviewID' => $review->reviewID,
                        'productName' => $review->product->name ?? 'Unknown Product',
                        'productDescription' => $review->product->description ?? '',
                        'rating' => (int) $review->rating,
                        'comment' => $review->comment,
                        'isAnonymous' => (bool) $review->is_anonymous,
                        'status' => $review->status,
                        'created_at' => $review->created_at->toISOString(),
                        'time_ago' => $review->created_at->diffForHumans()
                    ];
                });

            return response()->json([
                'success' => true,
                'reviews' => $reviews,
                'totalReviews' => $reviews->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching user reviews: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch your reviews'
            ], 500);
        }
    }

    /**
     * Update user's review
     */
    public function updateReview(Request $request, $reviewID)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please login to update review'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
                'isAnonymous' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please check your input',
                    'errors' => $validator->errors()
                ], 422);
            }

            $review = ProductReview::where('reviewID', $reviewID)
                ->where('userID', $user->userID)
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found or you do not have permission to update it'
                ], 404);
            }

            $review->update([
                'rating' => $request->rating,
                'comment' => $request->comment ?? '',
                'is_anonymous' => $request->isAnonymous ?? false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Review updated successfully!',
                'review' => [
                    'reviewID' => $review->reviewID,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'isAnonymous' => $review->is_anonymous
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating review: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review'
            ], 500);
        }
    }

    /**
     * Delete user's review
     */
    public function deleteReview($reviewID)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please login to delete review'
                ], 401);
            }

            $review = ProductReview::where('reviewID', $reviewID)
                ->where('userID', $user->userID)
                ->first();

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found or you do not have permission to delete it'
                ], 404);
            }

            $review->delete();

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting review: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review'
            ], 500);
        }
    }
}