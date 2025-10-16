<?php

namespace App\Http\Controllers;

use App\Models\MedicalSupply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MedicalSupplyController extends Controller
{
    /**
     * Get medical supplies for patient
     */
    public function getMedicalSuppliesForPatient(Request $request)
    {
        try {
            $query = MedicalSupply::active();

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%");
                });
            }

            // Category filter
            if ($request->has('category') && $request->category) {
                $query->where('category', $request->category);
            }

            $supplies = $query->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'supplies' => $supplies->map(function($supply) {
                    // Fix image URL generation
                    $imageUrl = null;
                    if ($supply->image) {
                        // Use asset() helper to generate correct URL
                        $imageUrl = asset('assets/images/Medical supplies/' . $supply->image);
                    } else {
                        // Fallback placeholder
                        $imageUrl = asset('assets/images/medical-placeholder.png');
                    }

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
                        'imageUrl' => $imageUrl, // Use the corrected URL
                        'status' => $supply->status,
                        'isLowStock' => $supply->isLowStock,
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching medical supplies for patient: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch medical supplies'
            ], 500);
        }
    }

    /**
     * Get single medical supply for patient
     */
    public function getMedicalSupplyForPatient($id)
    {
        try {
            $supply = MedicalSupply::active()->where('supplyID', $id)->first();

            if (!$supply) {
                return response()->json([
                    'success' => false,
                    'message' => 'Medical supply not found'
                ], 404);
            }

            // Fix image URL generation
            $imageUrl = null;
            if ($supply->image) {
                $imageUrl = asset('assets/images/Medical supplies/' . $supply->image);
            } else {
                $imageUrl = asset('assets/images/medical-placeholder.png');
            }

            return response()->json([
                'success' => true,
                'supply' => [
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
                    'imageUrl' => $imageUrl, // Use the corrected URL
                    'status' => $supply->status,
                    'isLowStock' => $supply->isLowStock,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching medical supply for patient: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch medical supply'
            ], 500);
        }
    }

    /**
     * Get supplies by category for patient
     */
    public function getSuppliesByCategoryForPatient($category)
    {
        try {
            $supplies = MedicalSupply::active()
                ->where('category', $category)
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'supplies' => $supplies->map(function($supply) {
                    // Fix image URL generation
                    $imageUrl = null;
                    if ($supply->image) {
                        $imageUrl = asset('assets/images/Medical supplies/' . $supply->image);
                    } else {
                        $imageUrl = asset('assets/images/medical-placeholder.png');
                    }

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
                        'imageUrl' => $imageUrl, // Use the corrected URL
                        'status' => $supply->status,
                        'isLowStock' => $supply->isLowStock,
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching supplies by category for patient: ' . $e->getMessage());
            
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
            $request->validate([
                'q' => 'required|string|min:2'
            ]);

            $searchTerm = $request->q;

            $supplies = MedicalSupply::active()
                ->where(function($query) use ($searchTerm) {
                    $query->where('name', 'like', "%{$searchTerm}%")
                          ->orWhere('description', 'like', "%{$searchTerm}%")
                          ->orWhere('category', 'like', "%{$searchTerm}%");
                })
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'supplies' => $supplies->map(function($supply) {
                    // Fix image URL generation
                    $imageUrl = null;
                    if ($supply->image) {
                        $imageUrl = asset('assets/images/Medical supplies/' . $supply->image);
                    } else {
                        $imageUrl = asset('assets/images/medical-placeholder.png');
                    }

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
                        'imageUrl' => $imageUrl, // Use the corrected URL
                        'status' => $supply->status,
                        'isLowStock' => $supply->isLowStock,
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching medical supplies for patient: ' . $e->getMessage());
            
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
            $supply = MedicalSupply::active()->where('supplyID', $id)->first();

            if (!$supply) {
                return response()->json([
                    'success' => false,
                    'message' => 'Medical supply not found'
                ], 404);
            }

            // Get related items from database or fallback to same category
            $relatedSupplies = MedicalSupply::active()
                ->where('supplyID', '!=', $id)
                ->where('category', $supply->category)
                ->inRandomOrder()
                ->limit(4)
                ->get()
                ->map(function($relatedSupply) {
                    // Fix image URL generation
                    $imageUrl = null;
                    if ($relatedSupply->image) {
                        $imageUrl = asset('assets/images/Medical supplies/' . $relatedSupply->image);
                    } else {
                        $imageUrl = asset('assets/images/medical-placeholder.png');
                    }

                    return [
                        'supplyID' => $relatedSupply->supplyID,
                        'name' => $relatedSupply->name,
                        'category' => $relatedSupply->category,
                        'price' => (float) $relatedSupply->price,
                        'stock' => (int) $relatedSupply->stock,
                        'image' => $relatedSupply->image,
                        'imageUrl' => $imageUrl, // Use the corrected URL
                        'isLowStock' => $relatedSupply->isLowStock,
                    ];
                });

            return response()->json([
                'success' => true,
                'relatedSupplies' => $relatedSupplies
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching related supplies: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch related supplies'
            ], 500);
        }
    }

    /**
     * Add new medical supply
     */
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
                
                // Define the storage path - using public path for direct access
                $storagePath = public_path('assets/images/Medical supplies');
                
                // Create directory if it doesn't exist
                if (!file_exists($storagePath)) {
                    mkdir($storagePath, 0755, true);
                }
                
                // Move image to public directory
                $image->move($storagePath, $imageName);
                
                Log::info('Image stored successfully', [
                    'path' => $storagePath,
                    'filename' => $imageName
                ]);
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
                'status' => 'active',
                'expiryDate' => $request->expiryDate ?? null,
            ]);

            DB::commit();

            Log::info('New medical supply added successfully', [
                'supplyID' => $supply->supplyID,
                'name' => $supply->name,
                'category' => $supply->category,
                'supplier' => $supply->supplier,
                'image' => $imageName
            ]);

            // Generate correct image URL for response
            $imageUrl = asset('assets/images/Medical supplies/' . $imageName);

            return response()->json([
                'success' => true,
                'message' => 'Medical supply added successfully',
                'supply' => [
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
                    'imageUrl' => $imageUrl, // Use the corrected URL
                    'status' => $supply->status,
                    'isLowStock' => $supply->isLowStock,
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to add medical supply: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            // Delete uploaded image if record creation failed
            if (!empty($imageName)) {
                try {
                    $imagePath = public_path('assets/images/Medical supplies/' . $imageName);
                    if (file_exists($imagePath)) {
                        unlink($imagePath);
                    }
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

    /**
     * Get all supplies for staff
     */
    public function getSupplies(Request $request)
    {
        try {
            $query = MedicalSupply::query();

            // Search functionality
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('category', 'like', "%{$search}%")
                      ->orWhere('supplyID', 'like', "%{$search}%");
                });
            }

            // Category filter
            if ($request->has('category') && $request->category) {
                $query->where('category', $request->category);
            }

            // Status filter
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }

            // Stock status filter
            if ($request->has('stock_status') && $request->stock_status) {
                if ($request->stock_status === 'low') {
                    $query->whereRaw('stock <= minStock');
                } elseif ($request->stock_status === 'out') {
                    $query->where('stock', 0);
                } elseif ($request->stock_status === 'adequate') {
                    $query->whereRaw('stock > minStock');
                }
            }

            $supplies = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'supplies' => $supplies->map(function($supply) {
                    // Fix image URL generation
                    $imageUrl = null;
                    if ($supply->image) {
                        $imageUrl = asset('assets/images/Medical supplies/' . $supply->image);
                    } else {
                        $imageUrl = asset('assets/images/medical-placeholder.png');
                    }

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
                        'imageUrl' => $imageUrl, // Use the corrected URL
                        'status' => $supply->status,
                        'isLowStock' => $supply->isLowStock,
                        'created_at' => $supply->created_at,
                        'updated_at' => $supply->updated_at,
                    ];
                })
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching medical supplies: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch medical supplies'
            ], 500);
        }
    }

    /**
     * Get single supply
     */
    public function getSupply($id)
    {
        try {
            $supply = MedicalSupply::where('supplyID', $id)->first();

            if (!$supply) {
                return response()->json([
                    'success' => false,
                    'message' => 'Medical supply not found'
                ], 404);
            }

            // Fix image URL generation
            $imageUrl = null;
            if ($supply->image) {
                $imageUrl = asset('assets/images/Medical supplies/' . $supply->image);
            } else {
                $imageUrl = asset('assets/images/medical-placeholder.png');
            }

            return response()->json([
                'success' => true,
                'supply' => [
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
                    'imageUrl' => $imageUrl, // Use the corrected URL
                    'status' => $supply->status,
                    'isLowStock' => $supply->isLowStock,
                    'created_at' => $supply->created_at,
                    'updated_at' => $supply->updated_at,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching medical supply: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch medical supply'
            ], 500);
        }
    }

    /**
     * Update medical supply
     */
    public function updateSupply(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'stock' => 'required|integer|min:0',
                'minStock' => 'required|integer|min:1',
                'price' => 'required|numeric|min:0',
                'supplier' => 'nullable|string|max:255',
                'expiryDate' => 'nullable|date',
                'category' => 'required|string|max:255',
                'status' => 'required|in:active,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $supply = MedicalSupply::where('supplyID', $id)->first();

            if (!$supply) {
                return response()->json([
                    'success' => false,
                    'message' => 'Medical supply not found'
                ], 404);
            }

            $supply->update([
                'name' => $request->name,
                'description' => $request->description,
                'stock' => $request->stock,
                'minStock' => $request->minStock,
                'price' => $request->price,
                'supplier' => $request->supplier,
                'expiryDate' => $request->expiryDate,
                'category' => $request->category,
                'status' => $request->status,
            ]);

            // Fix image URL generation
            $imageUrl = null;
            if ($supply->image) {
                $imageUrl = asset('assets/images/Medical supplies/' . $supply->image);
            } else {
                $imageUrl = asset('assets/images/medical-placeholder.png');
            }

            return response()->json([
                'success' => true,
                'message' => 'Medical supply updated successfully',
                'supply' => [
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
                    'imageUrl' => $imageUrl,
                    'status' => $supply->status,
                    'isLowStock' => $supply->isLowStock,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating medical supply: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update medical supply'
            ], 500);
        }
    }

    /**
     * Delete medical supply
     */
    public function deleteSupply($id)
    {
        try {
            $supply = MedicalSupply::where('supplyID', $id)->first();

            if (!$supply) {
                return response()->json([
                    'success' => false,
                    'message' => 'Medical supply not found'
                ], 404);
            }

            // Delete associated image if exists
            if ($supply->image) {
                $imagePath = public_path('assets/images/Medical supplies/' . $supply->image);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $supply->delete();

            return response()->json([
                'success' => true,
                'message' => 'Medical supply deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting medical supply: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete medical supply'
            ], 500);
        }
    }

    /**
     * Quick update supply
     */
    public function quickUpdateSupply(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'stock' => 'required|integer|min:0',
                'minStock' => 'required|integer|min:1',
                'price' => 'required|numeric|min:0',
                'supplier' => 'nullable|string|max:255',
                'expiryDate' => 'nullable|date',
                'category' => 'required|string|max:255',
                'status' => 'required|in:active,inactive',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $supply = MedicalSupply::where('supplyID', $id)->first();

            if (!$supply) {
                return response()->json([
                    'success' => false,
                    'message' => 'Medical supply not found'
                ], 404);
            }

            $supply->update([
                'name' => $request->name,
                'description' => $request->description,
                'stock' => $request->stock,
                'minStock' => $request->minStock,
                'price' => $request->price,
                'supplier' => $request->supplier,
                'expiryDate' => $request->expiryDate,
                'category' => $request->category,
                'status' => $request->status,
            ]);

            // Fix image URL generation
            $imageUrl = null;
            if ($supply->image) {
                $imageUrl = asset('assets/images/Medical supplies/' . $supply->image);
            } else {
                $imageUrl = asset('assets/images/medical-placeholder.png');
            }

            return response()->json([
                'success' => true,
                'message' => 'Medical supply updated successfully',
                'supply' => [
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
                    'imageUrl' => $imageUrl,
                    'status' => $supply->status,
                    'isLowStock' => $supply->isLowStock,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating medical supply: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update medical supply'
            ], 500);
        }
    }

    /**
     * Get analytics dashboard data
     */
    public function getAnalytics()
    {
        try {
            $totalSupplies = MedicalSupply::count();
            $activeSupplies = MedicalSupply::where('status', 'active')->count();
            $lowStockSupplies = MedicalSupply::whereRaw('stock <= minStock')->where('stock', '>', 0)->count();
            $outOfStockSupplies = MedicalSupply::where('stock', 0)->count();
            
            $totalInventoryValue = MedicalSupply::where('status', 'active')->sum(\DB::raw('stock * price'));
            $categories = MedicalSupply::groupBy('category')
                ->selectRaw('category, COUNT(*) as count')
                ->get();

            return response()->json([
                'success' => true,
                'analytics' => [
                    'totalSupplies' => $totalSupplies,
                    'activeSupplies' => $activeSupplies,
                    'lowStockSupplies' => $lowStockSupplies,
                    'outOfStockSupplies' => $outOfStockSupplies,
                    'totalInventoryValue' => (float) $totalInventoryValue,
                    'categories' => $categories,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching analytics: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics'
            ], 500);
        }
    }

    /**
     * Get low stock supplies
     */
    public function getLowStockSupplies()
    {
        try {
            $lowStockSupplies = MedicalSupply::whereRaw('stock <= minStock')
                ->where('stock', '>', 0)
                ->orderByRaw('stock / minStock ASC')
                ->get()
                ->map(function($supply) {
                    // Fix image URL generation
                    $imageUrl = null;
                    if ($supply->image) {
                        $imageUrl = asset('assets/images/Medical supplies/' . $supply->image);
                    } else {
                        $imageUrl = asset('assets/images/medical-placeholder.png');
                    }

                    return [
                        'supplyID' => $supply->supplyID,
                        'name' => $supply->name,
                        'category' => $supply->category,
                        'stock' => (int) $supply->stock,
                        'minStock' => (int) $supply->minStock,
                        'price' => (float) $supply->price,
                        'imageUrl' => $imageUrl, // Use the corrected URL
                        'status' => $supply->status,
                        'isLowStock' => $supply->isLowStock,
                    ];
                });

            return response()->json([
                'success' => true,
                'supplies' => $lowStockSupplies
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching low stock supplies: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch low stock supplies'
            ], 500);
        }
    }
}