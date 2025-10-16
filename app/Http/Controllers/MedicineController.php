<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MedicineController extends Controller
{
    public function searchMedicines(Request $request)
    {
        try {
            $searchTerm = $request->query('q', '');
            
            if (empty($searchTerm)) {
                return response()->json([]);
            }

            $medicines = Medicine::where('name', 'like', "%{$searchTerm}%")
                ->orWhere('generic_name', 'like', "%{$searchTerm}%")
                ->limit(10)
                ->get(['id', 'name', 'generic_name', 'category', 'manufacturer', 'common_dosage', 'common_frequency', 'common_duration', 'common_instructions']);

            return response()->json($medicines);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search medicines: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addMedicine(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'generic_name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'category' => 'required|string|max:255',
                'manufacturer' => 'nullable|string|max:255',
                'common_dosage' => 'nullable|string|max:255',
                'common_frequency' => 'nullable|string|max:255',
                'common_duration' => 'nullable|string|max:255',
                'common_instructions' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $medicine = Medicine::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Medicine added successfully',
                'medicine' => $medicine
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add medicine: ' . $e->getMessage()
            ], 500);
        }
    }
}