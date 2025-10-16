<?php

namespace App\Http\Controllers;

use App\Models\ReadyMedicine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ReadyMedicineController extends Controller
{
    /**
     * Get all active ready medicines
     */
    public function getReadyMedicines(): JsonResponse
    {
        try {
            $medicines = ReadyMedicine::where('is_active', true)
                ->orderBy('medicine_name')
                ->get();

            Log::info('Fetched ready medicines count: ' . $medicines->count());

            return response()->json([
                'success' => true,
                'data' => $medicines
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching ready medicines: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ready medicines',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a ready medicine to prescription
     */
    public function addReadyMedicine(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'medicine_id' => 'required|integer|exists:ready_medicines,id',
                'patient_id' => 'required|integer|exists:patients,patientID'
            ]);

            $readyMedicine = ReadyMedicine::findOrFail($validated['medicine_id']);

            // Return the medicine details to be added to the prescription
            return response()->json([
                'success' => true,
                'message' => 'Ready medicine added successfully',
                'medicine' => [
                    'name' => $readyMedicine->medicine_name,
                    'dosage' => $readyMedicine->dosage,
                    'frequency' => $readyMedicine->frequency,
                    'duration' => $readyMedicine->duration,
                    'instructions' => $readyMedicine->instructions,
                    'is_ready_medicine' => true,
                    'ready_medicine_id' => $readyMedicine->id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error adding ready medicine: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to add ready medicine',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new ready medicine
     */
    public function createReadyMedicine(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'medicine_name' => 'required|string|max:500',
                'dosage' => 'required|string|max:255',
                'frequency' => 'required|string|max:255',
                'duration' => 'required|string|max:255',
                'instructions' => 'nullable|string'
            ]);

            $medicine = ReadyMedicine::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Ready medicine created successfully',
                'data' => $medicine
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating ready medicine: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create ready medicine',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update ready medicine
     */
    public function updateReadyMedicine(Request $request, $id): JsonResponse
    {
        try {
            $medicine = ReadyMedicine::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'medicine_name' => 'required|string|max:500',
                'dosage' => 'required|string|max:255',
                'frequency' => 'required|string|max:255',
                'duration' => 'required|string|max:255',
                'instructions' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $medicine->update($validator->validated());

            return response()->json([
                'success' => true,
                'message' => 'Ready medicine updated successfully',
                'data' => $medicine
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating ready medicine: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update ready medicine',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete ready medicine (soft delete)
     */
    public function deleteReadyMedicine($id): JsonResponse
    {
        try {
            $medicine = ReadyMedicine::findOrFail($id);
            $medicine->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Ready medicine deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting ready medicine: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ready medicine',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}