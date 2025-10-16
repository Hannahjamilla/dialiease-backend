<?php

namespace App\Http\Controllers;

use App\Models\MedicalSupply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MedsProdController extends Controller
{
    /**
     * Get all medical supplies for patient with search and filtering
     */
    public function getMedicalSuppliesForPatient(Request $request)
    {
        try {
            Log::info('Fetching medical supplies for patient', ['request' => $request->all()]);

            $query = MedicalSupply::where('status', 'active');

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                      ->orWhere('description', 'like', "%{$searchTerm}%")
                      ->orWhere('category', 'like', "%{$searchTerm}%")
                      ->orWhere('supplier', 'like', "%{$searchTerm}%");
                });
                
                Log::info('Search filter applied', ['search_term' => $searchTerm]);
            }

            // Category filter
            if ($request->has('category') && !empty($request->category)) {
                $query->where('category', $request->category);
                Log::info('Category filter applied', ['category' => $request->category]);
            }

            // Price range filter
            if ($request->has('min_price') && $request->has('max_price')) {
                $minPrice = floatval($request->min_price);
                $maxPrice = floatval($request->max_price);
                $query->whereBetween('price', [$minPrice, $maxPrice]);
                Log::info('Price range filter applied', ['min_price' => $minPrice, 'max_price' => $maxPrice]);
            }

            // Stock availability filter
            if ($request->has('in_stock') && $request->in_stock == 'true') {
                $query->where('stock', '>', 0);
                Log::info('In stock filter applied');
            }

            // Sort functionality
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');

            switch ($sortBy) {
                case 'price':
                    $query->orderBy('price', $sortOrder);
                    break;
                case 'price-desc':
                    $query->orderBy('price', 'desc');
                    break;
                case 'stock':
                    $query->orderBy('stock', 'desc');
                    break;
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'rating':
                    // If you have rating system, add it here
                    $query->orderBy('name', 'asc');
                    break;
                default:
                    $query->orderBy('name', 'asc');
                    break;
            }

            $supplies = $query->get();

            Log::info('Medical supplies fetched successfully', ['count' => $supplies->count()]);

            return response()->json([
                'success' => true,
                'supplies' => $supplies->map(function($supply) {
                    return $this->formatSupplyData($supply);
                }),
                'total_count' => $supplies->count(),
                'filters_applied' => [
                    'search' => $request->search ?? null,
                    'category' => $request->category ?? null,
                    'min_price' => $request->min_price ?? null,
                    'max_price' => $request->max_price ?? null,
                    'sort_by' => $request->sort_by ?? 'name'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching medical supplies for patient: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch medical supplies',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Serve medical supply image - FIXED with proper path handling
     */
    public function serveImage($filename)
    {
        try {
            // Remove any path traversal attempts and decode URL encoding
            $filename = basename(urldecode($filename));
            
            Log::info('Serving medical supply image', ['filename' => $filename]);
            
            // Define multiple possible paths where images might be stored
            $possiblePaths = [
                public_path('assets/images/Medical supplies/' . $filename),
                public_path('assets/images/Medical supplies/' . $filename),
                storage_path('app/public/medical-supplies/' . $filename),
                public_path('medical-supplies/' . $filename),
            ];
            
            $imagePath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $imagePath = $path;
                    Log::info('Image found at path:', ['path' => $path]);
                    break;
                }
            }

            if (!$imagePath) {
                Log::warning('Medical supply image not found at any path', [
                    'filename' => $filename,
                    'searched_paths' => $possiblePaths
                ]);
                
                // Return a default placeholder image
                return $this->serveDefaultImage();
            }

            $mime = mime_content_type($imagePath);
            
            Log::info('Image found, serving with MIME type:', ['mime' => $mime]);
            
            // Set proper caching headers
            return response()->file($imagePath, [
                'Content-Type' => $mime,
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Access-Control-Allow-Origin' => '*'
            ]);

        } catch (\Exception $e) {
            Log::error('Error serving medical supply image: ' . $e->getMessage(), [
                'filename' => $filename,
                'trace' => $e->getTraceAsString()
            ]);
            
            return $this->serveDefaultImage();
        }
    }

    /**
     * Serve a default placeholder image
     */
    private function serveDefaultImage()
    {
        try {
            // Create a simple placeholder image
            $placeholder = imagecreate(400, 300);
            $backgroundColor = imagecolorallocate($placeholder, 245, 247, 250); // Light gray
            $textColor = imagecolorallocate($placeholder, 108, 117, 125); // Gray text
            
            // Add text to the placeholder
            imagestring($placeholder, 5, 120, 140, 'No Image Available', $textColor);
            
            // Output the image
            header('Content-Type: image/png');
            imagepng($placeholder);
            imagedestroy($placeholder);
            exit;
            
        } catch (\Exception $e) {
            // If GD is not available, return JSON response
            return response()->json([
                'success' => false,
                'message' => 'Image not found and placeholder generation failed',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get single medical supply for patient
     */
    public function getMedicalSupplyForPatient($id)
    {
        try {
            Log::info('Fetching single medical supply for patient', ['supply_id' => $id]);

            $supply = MedicalSupply::where('supplyID', $id)
                ->where('status', 'active')
                ->first();

            if (!$supply) {
                Log::warning('Medical supply not found', ['supply_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Medical supply not found or unavailable'
                ], 404);
            }

            Log::info('Medical supply fetched successfully', ['supply_id' => $id]);

            return response()->json([
                'success' => true,
                'supply' => $this->formatSupplyData($supply)
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching medical supply for patient: ' . $e->getMessage(), [
                'supply_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch medical supply'
            ], 500);
        }
    }

    /**
     * Get medical supplies by category for patient
     */
    public function getSuppliesByCategoryForPatient($category)
    {
        try {
            Log::info('Fetching medical supplies by category for patient', ['category' => $category]);

            $supplies = MedicalSupply::where('category', $category)
                ->where('status', 'active')
                ->orderBy('name')
                ->get();

            if ($supplies->isEmpty()) {
                Log::info('No supplies found for category', ['category' => $category]);
                return response()->json([
                    'success' => true,
                    'supplies' => [],
                    'message' => 'No supplies found in this category'
                ]);
            }

            Log::info('Category supplies fetched successfully', [
                'category' => $category,
                'count' => $supplies->count()
            ]);

            return response()->json([
                'success' => true,
                'supplies' => $supplies->map(function($supply) {
                    return $this->formatSupplyData($supply);
                }),
                'category' => $category,
                'total_count' => $supplies->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching supplies by category for patient: ' . $e->getMessage(), [
                'category' => $category,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch supplies by category'
            ], 500);
        }
    }

    /**
     * Search medical supplies for patient
     */
    public function searchMedicalSuppliesForPatient(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'q' => 'required|string|min:2|max:255'
            ], [
                'q.required' => 'Search query is required',
                'q.min' => 'Search query must be at least 2 characters',
                'q.max' => 'Search query is too long'
            ]);

            if ($validator->fails()) {
                Log::warning('Search validation failed', ['errors' => $validator->errors()->toArray()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $searchTerm = $request->q;
            Log::info('Searching medical supplies for patient', ['search_term' => $searchTerm]);

            $supplies = MedicalSupply::where('status', 'active')
                ->where(function($query) use ($searchTerm) {
                    $query->where('name', 'like', "%{$searchTerm}%")
                          ->orWhere('description', 'like', "%{$searchTerm}%")
                          ->orWhere('category', 'like', "%{$searchTerm}%")
                          ->orWhere('supplier', 'like', "%{$searchTerm}%");
                })
                ->orderBy('name')
                ->get();

            Log::info('Search completed', [
                'search_term' => $searchTerm,
                'results_count' => $supplies->count()
            ]);

            return response()->json([
                'success' => true,
                'supplies' => $supplies->map(function($supply) {
                    return $this->formatSupplyData($supply);
                }),
                'search_term' => $searchTerm,
                'total_results' => $supplies->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching medical supplies for patient: ' . $e->getMessage(), [
                'search_term' => $request->q ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to search medical supplies'
            ], 500);
        }
    }

    /**
     * Get related medical supplies
     */
    public function getRelatedSupplies($id)
    {
        try {
            Log::info('Fetching related medical supplies', ['supply_id' => $id]);

            $supply = MedicalSupply::where('supplyID', $id)
                ->where('status', 'active')
                ->first();

            if (!$supply) {
                Log::warning('Medical supply not found for related items', ['supply_id' => $id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Medical supply not found'
                ], 404);
            }

            // Get related supplies from same category, excluding current supply
            $relatedSupplies = MedicalSupply::where('category', $supply->category)
                ->where('supplyID', '!=', $id)
                ->where('status', 'active')
                ->where('stock', '>', 0)
                ->inRandomOrder()
                ->limit(6)
                ->get();

            Log::info('Related supplies fetched successfully', [
                'supply_id' => $id,
                'related_count' => $relatedSupplies->count()
            ]);

            return response()->json([
                'success' => true,
                'relatedSupplies' => $relatedSupplies->map(function($relatedSupply) {
                    return [
                        'supplyID' => $relatedSupply->supplyID,
                        'name' => $relatedSupply->name,
                        'category' => $relatedSupply->category,
                        'price' => (float) $relatedSupply->price,
                        'stock' => (int) $relatedSupply->stock,
                        'image' => $relatedSupply->image,
                        'imageUrl' => $this->getImageUrl($relatedSupply),
                        'isLowStock' => $relatedSupply->stock <= $relatedSupply->minStock,
                    ];
                }),
                'current_supply' => [
                    'name' => $supply->name,
                    'category' => $supply->category
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching related supplies: ' . $e->getMessage(), [
                'supply_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch related supplies'
            ], 500);
        }
    }

    /**
     * Get available categories for patient
     */
    public function getCategoriesForPatient()
    {
        try {
            Log::info('Fetching categories for patient');

            $categories = MedicalSupply::where('status', 'active')
                ->select('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category');

            Log::info('Categories fetched successfully', ['count' => $categories->count()]);

            return response()->json([
                'success' => true,
                'categories' => $categories,
                'total_categories' => $categories->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching categories for patient: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories'
            ], 500);
        }
    }

    /**
     * Get featured medical supplies (high stock, popular items)
     */
    public function getFeaturedSuppliesForPatient()
    {
        try {
            Log::info('Fetching featured medical supplies for patient');

            $featuredSupplies = MedicalSupply::where('status', 'active')
                ->where('stock', '>', 10) // Good stock level
                ->orderBy('stock', 'desc')
                ->limit(8)
                ->get();

            Log::info('Featured supplies fetched successfully', ['count' => $featuredSupplies->count()]);

            return response()->json([
                'success' => true,
                'featuredSupplies' => $featuredSupplies->map(function($supply) {
                    return $this->formatSupplyData($supply);
                }),
                'total_featured' => $featuredSupplies->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching featured supplies for patient: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch featured supplies'
            ], 500);
        }
    }

    /**
     * Get low stock supplies (for notification purposes)
     */
    public function getLowStockSuppliesForPatient()
    {
        try {
            Log::info('Fetching low stock medical supplies for patient');

            $lowStockSupplies = MedicalSupply::where('status', 'active')
                ->where('stock', '>', 0)
                ->whereRaw('stock <= minStock')
                ->orderBy('stock', 'asc')
                ->get();

            Log::info('Low stock supplies fetched successfully', ['count' => $lowStockSupplies->count()]);

            return response()->json([
                'success' => true,
                'lowStockSupplies' => $lowStockSupplies->map(function($supply) {
                    return [
                        'supplyID' => $supply->supplyID,
                        'name' => $supply->name,
                        'stock' => (int) $supply->stock,
                        'minStock' => (int) $supply->minStock,
                        'price' => (float) $supply->price,
                        'imageUrl' => $this->getImageUrl($supply),
                        'urgency' => $supply->stock == 0 ? 'out_of_stock' : 'low_stock'
                    ];
                }),
                'total_low_stock' => $lowStockSupplies->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching low stock supplies for patient: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch low stock supplies'
            ], 500);
        }
    }

    /**
     * Format supply data for consistent API response
     */
    private function formatSupplyData($supply)
    {
        return [
            'supplyID' => $supply->supplyID,
            'name' => $supply->name,
            'category' => $supply->category,
            'description' => $supply->description,
            'stock' => (int) $supply->stock,
            'minStock' => (int) $supply->minStock,
            'price' => (float) $supply->price,
            'supplier' => $supply->supplier,
            'expiryDate' => $supply->expiryDate,
            'image' => $supply->image,
            'imageUrl' => $this->getImageUrl($supply),
            'status' => $supply->status,
            'isLowStock' => $supply->stock <= $supply->minStock,
            'isOutOfStock' => $supply->stock == 0,
            'created_at' => $supply->created_at,
            'updated_at' => $supply->updated_at,
            // Additional fields for frontend
            'availability' => $supply->stock > 0 ? 'in_stock' : 'out_of_stock',
            'stock_status' => $this->getStockStatus($supply),
            'formatted_price' => '₱' . number_format($supply->price, 2)
        ];
    }

    /**
     * Get image URL for supply - FIXED to use the API route
     */
    private function getImageUrl($supply)
    {
        if ($supply->image && $supply->image !== 'null' && $supply->image !== '') {
            // Use the image serving API route
            $baseUrl = config('app.url');
            $encodedFilename = urlencode($supply->image);
            $imageUrl = $baseUrl . '/api/medical-supply-image/' . $encodedFilename;
            
            Log::info('Generated image URL', [
                'supply_id' => $supply->supplyID,
                'filename' => $supply->image,
                'encoded_filename' => $encodedFilename,
                'image_url' => $imageUrl
            ]);
            
            return $imageUrl;
        }
        
        // Return placeholder image if no image exists
        $placeholderUrl = config('app.url') . '/api/medical-supply-image/placeholder.png';
        Log::info('No image found, using placeholder', ['placeholder_url' => $placeholderUrl]);
        
        return $placeholderUrl;
    }

    /**
     * Get stock status text
     */
    private function getStockStatus($supply)
    {
        if ($supply->stock == 0) {
            return 'Out of Stock';
        } elseif ($supply->stock <= $supply->minStock) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }
}