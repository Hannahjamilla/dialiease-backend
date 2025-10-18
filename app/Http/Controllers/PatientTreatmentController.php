<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PatientTreatmentController extends Controller
{
    public function getTreatments(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check if user has patient record
            if (!$user->patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $patientId = $user->patient->id;

            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'search' => 'nullable|string|max:255',
                'status' => 'nullable|string|max:50',
                'dateFrom' => 'nullable|date',
                'dateTo' => 'nullable|date|after_or_equal:dateFrom',
                'page' => 'nullable|integer|min:1',
                'perPage' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            $query = Treatment::with(['inSolution', 'outSolution'])
                ->where('Patient_ID', $patientId)
                ->orderBy('TreatmentDate', 'desc');

            // Apply search filter
            if (!empty($validated['search'])) {
                $search = $validated['search'];
                $query->where(function($q) use ($search) {
                    $q->where('TreatmentStatus', 'like', "%$search%")
                      ->orWhereHas('inSolution', function($q) use ($search) {
                          $q->where('Dialysate', 'like', "%$search%");
                      })
                      ->orWhereHas('outSolution', function($q) use ($search) {
                          $q->where('Notes', 'like', "%$search%");
                      });
                });
            }

            // Apply status filter
            if (!empty($validated['status'])) {
                $query->where('TreatmentStatus', $validated['status']);
            }

            // Apply date range filter
            if (!empty($validated['dateFrom'])) {
                $query->whereDate('TreatmentDate', '>=', $validated['dateFrom']);
            }
            if (!empty($validated['dateTo'])) {
                $query->whereDate('TreatmentDate', '<=', $validated['dateTo']);
            }

            // Paginate results
            $perPage = $validated['perPage'] ?? 10;
            $treatments = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'treatments' => $treatments->items(),
                'pagination' => [
                    'total' => $treatments->total(),
                    'per_page' => $treatments->perPage(),
                    'current_page' => $treatments->currentPage(),
                    'last_page' => $treatments->lastPage(),
                    'from' => $treatments->firstItem(),
                    'to' => $treatments->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve treatments',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function getTreatmentStats(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check if user has patient record
            if (!$user->patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $patientId = $user->patient->id;

            // Get basic stats
            $totalTreatments = Treatment::where('Patient_ID', $patientId)->count();
            
            $lastTreatment = Treatment::where('Patient_ID', $patientId)
                ->orderBy('TreatmentDate', 'desc')
                ->first();

            // Get average volume in and balance for completed treatments
            $completedTreatments = Treatment::with(['inSolution', 'outSolution'])
                ->where('Patient_ID', $patientId)
                ->where('TreatmentStatus', 'Completed')
                ->get();

            $avgVolumeIn = $completedTreatments->avg(function($treatment) {
                return $treatment->inSolution->VolumeIn ?? 0;
            });

            $avgBalance = $completedTreatments->avg(function($treatment) {
                $volumeIn = $treatment->inSolution->VolumeIn ?? 0;
                $volumeOut = $treatment->outSolution->VolumeOut ?? 0;
                return $volumeOut - $volumeIn;
            });

            // Get treatment status distribution
            $statusDistribution = Treatment::where('Patient_ID', $patientId)
                ->selectRaw('TreatmentStatus, COUNT(*) as count')
                ->groupBy('TreatmentStatus')
                ->get()
                ->pluck('count', 'TreatmentStatus');

            return response()->json([
                'success' => true,
                'stats' => [
                    'totalTreatments' => $totalTreatments,
                    'avgVolumeIn' => round($avgVolumeIn, 1),
                    'avgBalance' => round($avgBalance, 1),
                    'lastTreatmentDate' => $lastTreatment ? $lastTreatment->TreatmentDate : null,
                    'statusDistribution' => $statusDistribution
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve treatment statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get single treatment detail
     */
    public function getTreatmentDetail($id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            if (!$user->patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $treatment = Treatment::with(['inSolution', 'outSolution'])
                ->where('Patient_ID', $user->patient->id)
                ->where('id', $id)
                ->firstOrFail();

            return response()->json([
                'success' => true,
                'treatment' => $treatment
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Treatment not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve treatment details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}