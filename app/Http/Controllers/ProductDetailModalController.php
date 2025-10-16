<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ProductDetailModalController extends Controller
{
    /**
     * Get product details by ID
     */
    public function getProduct($id)
    {
        try {
            Log::info('Fetching product with ID: ' . $id);

            $product = DB::table('medical_supplies')
                ->where('supplyID', $id)
                ->orWhere('id', $id)
                ->first();

            if (!$product) {
                Log::warning('Product not found with ID: ' . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            Log::info('Product found: ' . $product->name);

            return response()->json([
                'success' => true,
                'product' => $product
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product details'
            ], 500);
        }
    }

    /**
     * Update product details
     */
    public function updateProduct(Request $request, $id)
    {
        try {
            Log::info('Updating product with ID: ' . $id, $request->all());

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
                Log::warning('Validation failed for product update', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if product exists
            $product = DB::table('medical_supplies')
                ->where('supplyID', $id)
                ->orWhere('id', $id)
                ->first();

            if (!$product) {
                Log::warning('Product not found for update with ID: ' . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
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
                // Fetch updated product
                $updatedProduct = DB::table('medical_supplies')
                    ->where('supplyID', $id)
                    ->orWhere('id', $id)
                    ->first();

                Log::info('Product updated successfully: ' . $updatedProduct->name);

                return response()->json([
                    'success' => true,
                    'message' => 'Product updated successfully',
                    'product' => $updatedProduct
                ]);
            } else {
                Log::warning('No changes made to product with ID: ' . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'No changes were made to the product'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error updating product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quick update specific field
     */
    public function quickUpdate(Request $request, $id)
    {
        try {
            Log::info('Quick updating product with ID: ' . $id, $request->all());

            $validator = Validator::make($request->all(), [
                'field' => 'required|string|in:name,description,stock,minStock,price,supplier,expiryDate,category',
                'value' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid field or value'
                ], 422);
            }

            $field = $request->input('field');
            $value = $request->input('value');

            // Additional field-specific validation
            $fieldValidations = [
                'stock' => 'integer|min:0',
                'minStock' => 'integer|min:1',
                'price' => 'numeric|min:0',
                'expiryDate' => 'date',
                'name' => 'string|max:255',
                'description' => 'string',
                'supplier' => 'string|max:255',
                'category' => 'string|max:255'
            ];

            $fieldValidator = Validator::make([$field => $value], [
                $field => $fieldValidations[$field]
            ]);

            if ($fieldValidator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid value for ' . $field,
                    'errors' => $fieldValidator->errors()
                ], 422);
            }

            // Check if product exists
            $product = DB::table('medical_supplies')
                ->where('supplyID', $id)
                ->orWhere('id', $id)
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Perform update
            $updated = DB::table('medical_supplies')
                ->where('supplyID', $id)
                ->orWhere('id', $id)
                ->update([
                    $field => $value,
                    'updated_at' => now()
                ]);

            if ($updated) {
                // Fetch updated product
                $updatedProduct = DB::table('medical_supplies')
                    ->where('supplyID', $id)
                    ->orWhere('id', $id)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => ucfirst($field) . ' updated successfully',
                    'product' => $updatedProduct
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update ' . $field
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error in quick update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product'
            ], 500);
        }
    }

    /**
     * Delete product
     */
    public function deleteProduct($id)
    {
        try {
            Log::info('Deleting product with ID: ' . $id);

            // Check if product exists
            $product = DB::table('medical_supplies')
                ->where('supplyID', $id)
                ->orWhere('id', $id)
                ->first();

            if (!$product) {
                Log::warning('Product not found for deletion with ID: ' . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Perform delete
            $deleted = DB::table('medical_supplies')
                ->where('supplyID', $id)
                ->orWhere('id', $id)
                ->delete();

            if ($deleted) {
                Log::info('Product deleted successfully: ' . $product->name);
                return response()->json([
                    'success' => true,
                    'message' => 'Product deleted successfully'
                ]);
            } else {
                Log::warning('Failed to delete product with ID: ' . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete product'
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error deleting product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product'
            ], 500);
        }
    }
}