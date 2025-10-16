<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use Illuminate\Http\Request;

class ReadyPrescriptionController extends Controller
{
    public function getReadyPrescriptions()
    {
        try {
            // Get medicines that are commonly used in ready prescriptions with all details
            $readyMedicines = Medicine::whereIn('name', [
                'SEVELAMER',
                'FEBUXOSTAT',
                'CALCITRIOL',
                'SACUBITRIL/VALSARTAN',
                'PARACETAMOL + TRAMADOL (DOLCET)',
                'SODIUM BICARBONATE',
                'CALCIUM CARBONATE',
                'FERROUS SULFATE',
                'FOLIC ACID',
                'ERYTHROPOIETIN BETA',
                'LACTULOSE',
                'CLOPIDOGREL',
                'ATORVASTATIN CALCIUM',
                'ISOSORBIDE 5-MONONITRATE',
                'CARVEDILOL'
            ])->get(['id', 'name', 'generic_name', 'common_dosage', 'common_frequency', 'common_duration', 'common_instructions', 'category', 'manufacturer']);

            return response()->json($readyMedicines);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ready prescriptions: ' . $e->getMessage()
            ], 500);
        }
    }

    public function applyReadyPrescription()
    {
        try {
            // Get the ready prescription medicines with complete prescription details
            $readyMedicines = Medicine::whereIn('name', [
                'SEVELAMER',
                'FEBUXOSTAT',
                'CALCITRIOL',
                'SACUBITRIL/VALSARTAN',
                'PARACETAMOL + TRAMADOL (DOLCET)',
                'SODIUM BICARBONATE',
                'CALCIUM CARBONATE',
                'FERROUS SULFATE',
                'FOLIC ACID',
                'ERYTHROPOIETIN BETA',
                'LACTULOSE',
                'CLOPIDOGREL',
                'ATORVASTATIN CALCIUM',
                'ISOSORBIDE 5-MONONITRATE',
                'CARVEDILOL'
            ])->get();

            if ($readyMedicines->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No ready prescription medicines found in database'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'medicines' => $readyMedicines
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to apply ready prescription: ' . $e->getMessage()
            ], 500);
        }
    }
}