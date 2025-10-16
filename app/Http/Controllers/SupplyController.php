<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\MedicalSupply;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class SupplyController extends Controller
{
    public function addSupply(Request $request)
    {
        Log::info('Add Supply Request Received', $request->all());
        
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'description' => 'required|string',
            'stock' => 'required|integer|min:0',
            'minStock' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'supplier' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ], [
            'name.required' => 'Supply name is required',
            'category.required' => 'Category is required',
            'description.required' => 'Description is required',
            'stock.required' => 'Stock quantity is required',
            'stock.integer' => 'Stock must be a whole number',
            'stock.min' => 'Stock cannot be negative',
            'minStock.required' => 'Minimum stock level is required',
            'minStock.integer' => 'Minimum stock must be a whole number',
            'minStock.min' => 'Minimum stock must be at least 1',
            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a number',
            'price.min' => 'Price cannot be negative',
            'supplier.required' => 'Supplier information is required',
            'image.required' => 'Supply image is required',
            'image.image' => 'The file must be an image',
            'image.mimes' => 'The image must be a JPEG, PNG, JPG, or GIF',
            'image.max' => 'The image size must not exceed 2MB'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed', ['errors' => $validator->errors()->toArray()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();
            
            $imageName = null;

            // Handle image upload
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $image = $request->file('image');
                
                // Generate unique filename
                $imageName = 'CAPD_stock_' . time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
                
                // Define the storage path - using storage/app/public for better Laravel compatibility
                $storagePath = 'public/assets/images/medical-supplies';
                
                // Store the image using Laravel's storage system
                $imagePath = $image->storeAs($storagePath, $imageName);
                
                Log::info('Image stored successfully', [
                    'path' => $imagePath,
                    'filename' => $imageName
                ]);
                
                // For public access, we'll use the filename only in the database
                // The full path will be handled by Laravel's storage system
            } else {
                throw new \Exception('Image file is required and must be valid');
            }

            // Create supply record with proper data types
            $supply = MedicalSupply::create([
                'name' => trim($request->name),
                'category' => trim($request->category),
                'description' => trim($request->description),
                'stock' => (int) $request->stock,
                'minStock' => (int) $request->minStock,
                'price' => round((float) $request->price, 2),
                'supplier' => trim($request->supplier),
                'image' => $imageName, // Store only filename
                'status' => 'active'
            ]);

            DB::commit();

            Log::info('New medical supply added successfully', [
                'supplyID' => $supply->supplyID,
                'name' => $supply->name,
                'category' => $supply->category,
                'supplier' => $supply->supplier,
                'image' => $imageName
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Medical supply added successfully',
                'supply' => $supply
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to add medical supply: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            // Delete uploaded image if record creation failed
            if (!empty($imageName)) {
                try {
                    Storage::delete("public/assets/images/medical-supplies/{$imageName}");
                } catch (\Exception $deleteError) {
                    Log::error('Failed to delete image: ' . $deleteError->getMessage());
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to add medical supply: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSupplies(Request $request)
    {
        try {
            $query = MedicalSupply::where('status', 'active');
            
            if ($request->has('category') && $request->category !== 'all') {
                $query->where('category', $request->category);
            }
            
            if ($request->has('search') && !empty($request->search)) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $supplies = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'supplies' => $supplies,
                'total' => $supplies->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch medical supplies: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch medical supplies'
            ], 500);
        }
    }

    public function getSupply($id)
    {
        try {
            $supply = MedicalSupply::where('supplyID', $id)->where('status', 'active')->first();

            if (!$supply) {
                return response()->json([
                    'success' => false,
                    'message' => 'Supply not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'supply' => $supply
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch supply: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch supply details'
            ], 500);
        }
    }


    public function getLowStockSupplies()
    {
        try {
            $lowStockSupplies = MedicalSupply::where('status', 'active')
                ->whereRaw('stock <= minStock')
                ->orderBy('stock', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'supplies' => $lowStockSupplies,
                'count' => $lowStockSupplies->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch low stock supplies: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch low stock supplies'
            ], 500);
        }
    }

    public function getAnalytics()
    {
        try {
            DB::beginTransaction();

            // Total supplies count
            $totalSupplies = MedicalSupply::where('status', 'active')->count();
            
            // Low stock count (stock <= minStock but not zero)
            $lowStockCount = MedicalSupply::where('status', 'active')
                ->where('stock', '>', 0)
                ->whereRaw('stock <= minStock')
                ->count();
            
            // Out of stock count
            $outOfStockCount = MedicalSupply::where('status', 'active')
                ->where('stock', 0)
                ->count();
            
            // In stock count
            $inStockCount = MedicalSupply::where('status', 'active')
                ->where('stock', '>', 0)
                ->whereRaw('stock > minStock')
                ->count();
            
            // Total inventory value - handle null prices
            $totalValue = MedicalSupply::where('status', 'active')
                ->whereNotNull('price')
                ->sum(DB::raw('COALESCE(stock, 0) * COALESCE(price, 0)'));
            
            // Category distribution
            $categoryDistribution = MedicalSupply::where('status', 'active')
                ->select('category', DB::raw('COUNT(*) as count'))
                ->groupBy('category')
                ->get()
                ->mapWithKeys(function($item) {
                    return [$item->category => $item->count];
                })->toArray();
            
            // Stock status breakdown
            $stockStatus = [
                'in_stock' => $inStockCount,
                'low_stock' => $lowStockCount,
                'out_of_stock' => $outOfStockCount
            ];
            
            // Recent low stock items (last 10)
            $recentLowStock = MedicalSupply::where('status', 'active')
                ->where(function($query) {
                    $query->where('stock', 0)
                          ->orWhereRaw('stock <= minStock');
                })
                ->orderBy('stock', 'asc')
                ->limit(10)
                ->get(['supplyID', 'name', 'stock', 'minStock', 'price', 'category']);
            
            // Monthly stock value trend (last 6 months) - simplified for now
            $monthlyTrend = MedicalSupply::where('status', 'active')
                ->where('created_at', '>=', now()->subMonths(6))
                ->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('SUM(COALESCE(stock, 0) * COALESCE(price, 0)) as total_value')
                )
                ->groupBy('year', 'month')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(6)
                ->get();

            // If no monthly trend data, create some sample data based on current inventory
            if ($monthlyTrend->isEmpty()) {
                $currentValue = $totalValue;
                $monthlyTrend = collect();
                for ($i = 5; $i >= 0; $i--) {
                    $month = now()->subMonths($i);
                    $monthlyTrend->push([
                        'year' => $month->year,
                        'month' => $month->month,
                        'total_value' => $currentValue * (0.8 + (mt_rand(0, 40) / 100)) // Random variation
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'analytics' => [
                    'totalSupplies' => $totalSupplies,
                    'lowStockCount' => $lowStockCount,
                    'outOfStockCount' => $outOfStockCount,
                    'inStockCount' => $inStockCount,
                    'totalValue' => round($totalValue, 2),
                    'categoryDistribution' => $categoryDistribution,
                    'stockStatus' => $stockStatus,
                    'recentLowStock' => $recentLowStock,
                    'monthlyTrend' => $monthlyTrend
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to fetch analytics: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function quickUpdateSupply(Request $request, $id)
    {
        Log::info('Quick Update Supply Request Received', ['id' => $id, 'data' => $request->all()]);
        
        $validator = Validator::make($request->all(), [
            'field' => 'required|in:name,description,stock,minStock,price,supplier,expiryDate',
            'value' => 'required'
        ], [
            'field.required' => 'Field to update is required',
            'field.in' => 'Field must be one of: name, description, stock, minStock, price, supplier, expiryDate',
            'value.required' => 'Value is required'
        ]);

        if ($validator->fails()) {
            Log::error('Quick update validation failed', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $supply = MedicalSupply::where('supplyID', $id)->where('status', 'active')->first();

            if (!$supply) {
                Log::warning('Supply not found for quick update', ['id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Supply not found'
                ], 404);
            }

            $field = $request->field;
            $value = $request->value;

            Log::info('Processing quick update', [
                'field' => $field,
                'value' => $value,
                'supply_id' => $id
            ]);

            // Additional field-specific validation
            $validationErrors = [];
            switch ($field) {
                case 'stock':
                    if (!is_numeric($value) || $value < 0) {
                        $validationErrors[] = 'Stock must be a non-negative number';
                    } else {
                        $value = (int) $value;
                    }
                    break;

                case 'minStock':
                    if (!is_numeric($value) || $value < 1) {
                        $validationErrors[] = 'Minimum stock must be at least 1';
                    } else {
                        $value = (int) $value;
                    }
                    break;

                case 'price':
                    if (!is_numeric($value) || $value < 0) {
                        $validationErrors[] = 'Price must be a non-negative number';
                    } else {
                        $value = (float) $value;
                    }
                    break;

                case 'expiryDate':
                    if ($value && $value !== 'null') {
                        try {
                            $expiryDate = \Carbon\Carbon::parse($value);
                            if ($expiryDate->isPast()) {
                                $validationErrors[] = 'Expiry date cannot be in the past';
                            }
                        } catch (\Exception $e) {
                            $validationErrors[] = 'Invalid date format';
                        }
                    } else {
                        $value = null; // Handle empty expiry date
                    }
                    break;

                case 'name':
                case 'description':
                case 'supplier':
                    // String fields - ensure they're not empty
                    if (empty(trim($value))) {
                        $validationErrors[] = ucfirst($field) . ' cannot be empty';
                    }
                    break;
            }

            if (!empty($validationErrors)) {
                Log::warning('Field validation failed', ['errors' => $validationErrors]);
                return response()->json([
                    'success' => false,
                    'message' => implode(', ', $validationErrors)
                ], 422);
            }

            // Update the specific field
            $updateData = [$field => $value];
            $supply->update($updateData);

            // Refresh the model to get updated data
            $supply->refresh();

            Log::info('Medical supply quick updated successfully', [
                'supplyID' => $supply->supplyID,
                'field' => $field,
                'value' => $value,
                'updated_supply' => $supply->toArray()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Supply updated successfully',
                'supply' => $supply
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to quick update medical supply: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update supply: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==============================================
    // STAFF-SIDE REVIEW MANAGEMENT FUNCTIONS
    // ==============================================

    public function getAllReviews(Request $request)
    {
        try {
            Log::info('Fetching all reviews for staff management', ['request' => $request->all()]);

            $query = ProductReview::with(['user', 'medicalSupply']);

            // Filter by status if provided
            if ($request->has('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->whereHas('user', function($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%");
                    })->orWhereHas('medicalSupply', function($supplyQuery) use ($search) {
                        $supplyQuery->where('name', 'like', "%{$search}%");
                    })->orWhere('comment', 'like', "%{$search}%");
                });
            }

            $reviews = $query->orderBy('created_at', 'desc')->get();

            Log::info('Reviews fetched successfully', ['count' => $reviews->count()]);

            return response()->json([
                'success' => true,
                'reviews' => $reviews->map(function($review) {
                    return [
                        'reviewID' => $review->reviewID,
                        'userName' => $review->user->name ?? 'Anonymous',
                        'supplyID' => $review->supplyID,
                        'productName' => $review->medicalSupply->name ?? 'Unknown Product',
                        'rating' => (int) $review->rating,
                        'comment' => $review->comment,
                        'video_path' => $review->video_path ? Storage::url($review->video_path) : null,
                        'status' => $review->status,
                        'created_at' => $review->created_at->toISOString(),
                        'updated_at' => $review->updated_at->toISOString(),
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching all reviews: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reviews: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateReviewStatus(Request $request, $reviewID)
    {
        try {
            Log::info('Updating review status', ['reviewID' => $reviewID, 'request' => $request->all()]);

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,approved,rejected'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $review = ProductReview::find($reviewID);

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found'
                ], 404);
            }

            $review->update([
                'status' => $request->status
            ]);

            Log::info('Review status updated successfully', ['reviewID' => $reviewID, 'status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => 'Review status updated successfully',
                'review' => [
                    'reviewID' => $review->reviewID,
                    'status' => $review->status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating review status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update review status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteReview($reviewID)
    {
        try {
            Log::info('Deleting review', ['reviewID' => $reviewID]);

            $review = ProductReview::find($reviewID);

            if (!$review) {
                return response()->json([
                    'success' => false,
                    'message' => 'Review not found'
                ], 404);
            }

            // Delete associated video file if exists
            if ($review->video_path && Storage::exists($review->video_path)) {
                Storage::delete($review->video_path);
            }

            $review->delete();

            Log::info('Review deleted successfully', ['reviewID' => $reviewID]);

            return response()->json([
                'success' => true,
                'message' => 'Review deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting review: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete review: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getReviewStats()
    {
        try {
            $totalReviews = ProductReview::count();
            $pendingReviews = ProductReview::where('status', 'pending')->count();
            $approvedReviews = ProductReview::where('status', 'approved')->count();
            $rejectedReviews = ProductReview::where('status', 'rejected')->count();
            $averageRating = ProductReview::where('status', 'approved')->avg('rating');

            return response()->json([
                'success' => true,
                'stats' => [
                    'totalReviews' => $totalReviews,
                    'pendingReviews' => $pendingReviews,
                    'approvedReviews' => $approvedReviews,
                    'rejectedReviews' => $rejectedReviews,
                    'averageRating' => round($averageRating, 1)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching review stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch review statistics'
            ], 500);
        }
    }

    public function getProductReviewsForStaff($supplyID)
    {
        try {
            $reviews = ProductReview::with(['user'])
                ->where('supplyID', $supplyID)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($review) {
                    return [
                        'reviewID' => $review->reviewID,
                        'userName' => $review->user->name ?? 'Anonymous',
                        'rating' => (int) $review->rating,
                        'comment' => $review->comment,
                        'videoUrl' => $review->video_path ? Storage::url($review->video_path) : null,
                        'status' => $review->status,
                        'created_at' => $review->created_at->toISOString(),
                        'updated_at' => $review->updated_at->toISOString(),
                        'time_ago' => $review->created_at->diffForHumans()
                    ];
                });

            $averageRating = ProductReview::where('supplyID', $supplyID)
                ->where('status', 'approved')
                ->avg('rating');

            $totalReviews = $reviews->count();
            $approvedReviews = $reviews->where('status', 'approved')->count();
            $pendingReviews = $reviews->where('status', 'pending')->count();

            return response()->json([
                'success' => true,
                'reviews' => $reviews,
                'stats' => [
                    'averageRating' => round($averageRating, 1),
                    'totalReviews' => $totalReviews,
                    'approvedReviews' => $approvedReviews,
                    'pendingReviews' => $pendingReviews
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching product reviews for staff: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product reviews'
            ], 500);
        }
    }

    /**
 * Update supply details
 */
public function updateSupply(Request $request, $id)
{
    try {
        Log::info('Updating supply with ID: ' . $id, $request->all());

        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'stock' => 'sometimes|required|integer|min:0',
            'minStock' => 'sometimes|required|integer|min:1',
            'price' => 'sometimes|required|numeric|min:0',
            'supplier' => 'sometimes|nullable|string|max:255',
            'expiryDate' => 'sometimes|nullable|date',
            'category' => 'sometimes|required|string|max:255'
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed for supply update', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if supply exists
        $supply = DB::table('medical_supplies')
            ->where('supplyID', $id)
            ->orWhere('id', $id)
            ->first();

        if (!$supply) {
            Log::warning('Supply not found for update with ID: ' . $id);
            return response()->json([
                'success' => false,
                'message' => 'Supply not found'
            ], 404);
        }

        // Prepare update data
        $updateData = [];
        $allowedFields = ['name', 'description', 'stock', 'minStock', 'price', 'supplier', 'expiryDate', 'category'];
        
        foreach ($allowedFields as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        // Add updated_at timestamp
        $updateData['updated_at'] = now();

        Log::info('Update data prepared:', $updateData);

        // Perform update
        $updated = DB::table('medical_supplies')
            ->where('supplyID', $id)
            ->orWhere('id', $id)
            ->update($updateData);

        if ($updated) {
            // Fetch updated supply
            $updatedSupply = DB::table('medical_supplies')
                ->where('supplyID', $id)
                ->orWhere('id', $id)
                ->first();

            Log::info('Supply updated successfully: ' . $updatedSupply->name);

            return response()->json([
                'success' => true,
                'message' => 'Supply updated successfully',
                'supply' => $updatedSupply
            ]);
        } else {
            Log::warning('No changes made to supply with ID: ' . $id);
            return response()->json([
                'success' => false,
                'message' => 'No changes were made to the supply'
            ]);
        }

    } catch (\Exception $e) {
        Log::error('Error updating supply: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to update supply: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Delete supply
 */
public function deleteSupply($id)
{
    try {
        Log::info('Deleting supply with ID: ' . $id);

        // Check if supply exists
        $supply = DB::table('medical_supplies')
            ->where('supplyID', $id)
            ->orWhere('id', $id)
            ->first();

        if (!$supply) {
            Log::warning('Supply not found for deletion with ID: ' . $id);
            return response()->json([
                'success' => false,
                'message' => 'Supply not found'
            ], 404);
        }

        // Perform delete
        $deleted = DB::table('medical_supplies')
            ->where('supplyID', $id)
            ->orWhere('id', $id)
            ->delete();

        if ($deleted) {
            Log::info('Supply deleted successfully: ' . $supply->name);
            return response()->json([
                'success' => true,
                'message' => 'Supply deleted successfully'
            ]);
        } else {
            Log::warning('Failed to delete supply with ID: ' . $id);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete supply'
            ]);
        }

    } catch (\Exception $e) {
        Log::error('Error deleting supply: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to delete supply'
        ], 500);
    }
}
}