<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StaffPatientTreatmentController extends Controller
{
    private function isAuthorized($user, $patientId)
    {
        if ($user->userLevel === 'admin') {
            return true;
        }

        return in_array($user->userLevel, ['staff', 'nurse']);
    }

    public function staffGetPatientTreatments($patientId)
    {
        try {
            $user = auth()->user();
            
            if (!$this->isAuthorized($user, $patientId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to patient records'
                ], 403);
            }

            // Check if the user exists and is a patient
            $patientExists = DB::table('users')
                ->where('userID', $patientId)
                ->where('userLevel', 'patient')
                ->exists();

            if (!$patientExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            // Get the patientID from the patients table
            $patientRecord = DB::table('patients')
                ->where('userID', $patientId)
                ->first();

            if (!$patientRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $query = DB::table('treatment')
                ->leftJoin('insolution', 'treatment.IN_ID', '=', 'insolution.IN_ID')
                ->leftJoin('outsolution', 'treatment.OUT_ID', '=', 'outsolution.OUT_ID')
                ->where('treatment.patientID', $patientRecord->patientID)
                ->select([
                    'treatment.Treatment_ID as id',
                    'treatment.treatmentDate as TreatmentDate',
                    'treatment.TreatmentStatus',
                    'insolution.Dialysate as in_dialysate',
                    'insolution.VolumeIn',
                    'insolution.InStarted',
                    'insolution.InFinished',
                    'insolution.Dwell as Dwell',
                    'outsolution.VolumeOut',
                    'outsolution.Notes',
                    'outsolution.Color',
                    'outsolution.DrainStarted',
                    'outsolution.DrainFinished',
                    'treatment.created_at'
                ]);

            if (request()->has('search') && request('search') !== '') {
                $search = request('search');
                $query->where(function($q) use ($search) {
                    $q->where('insolution.Dialysate', 'like', "%{$search}%")
                      ->orWhere('outsolution.Notes', 'like', "%{$search}%")
                      ->orWhere('treatment.TreatmentStatus', 'like', "%{$search}%")
                      ->orWhere('outsolution.Color', 'like', "%{$search}%");
                });
            }

            if (request()->has('status') && request('status') !== 'all') {
                $query->where('treatment.TreatmentStatus', request('status'));
            }

            if (request()->has('dateFrom') && request('dateFrom') !== '') {
                $query->whereDate('treatment.treatmentDate', '>=', request('dateFrom'));
            }

            if (request()->has('dateTo') && request('dateTo') !== '') {
                $query->whereDate('treatment.treatmentDate', '<=', request('dateTo'));
            }

            $perPage = request()->has('perPage') ? request('perPage') : 5;
            $treatments = $query->orderBy('treatment.treatmentDate', 'desc')
                                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'treatments' => $treatments->items(),
                'currentPage' => $treatments->currentPage(),
                'lastPage' => $treatments->lastPage(),
                'total' => $treatments->total()
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch treatments for patient ' . $patientId . ': ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch treatments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function staffGetTreatmentStats($patientId)
    {
        try {
            $user = auth()->user();
            
            if (!$this->isAuthorized($user, $patientId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to patient records'
                ], 403);
            }

            $patientExists = DB::table('users')
                ->where('userID', $patientId)
                ->where('userLevel', 'patient')
                ->exists();

            if (!$patientExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            // Get the patientID from the patients table
            $patientRecord = DB::table('patients')
                ->where('userID', $patientId)
                ->first();

            if (!$patientRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $totalTreatments = DB::table('treatment')
                ->where('patientID', $patientRecord->patientID)
                ->count();

            $completedTreatments = DB::table('treatment')
                ->leftJoin('insolution', 'treatment.IN_ID', '=', 'insolution.IN_ID')
                ->leftJoin('outsolution', 'treatment.OUT_ID', '=', 'outsolution.OUT_ID')
                ->where('treatment.patientID', $patientRecord->patientID)
                ->where('treatment.TreatmentStatus', 'Completed')
                ->select([
                    'insolution.VolumeIn',
                    'outsolution.VolumeOut'
                ])
                ->get();

            $avgVolumeIn = $completedTreatments->avg('VolumeIn') ?? 0;
            $avgVolumeOut = $completedTreatments->avg('VolumeOut') ?? 0;
            $avgBalance = $avgVolumeOut - $avgVolumeIn;

            $lastTreatment = DB::table('treatment')
                ->where('patientID', $patientRecord->patientID)
                ->orderBy('treatmentDate', 'desc')
                ->first();

            return response()->json([
                'success' => true,
                'stats' => [
                    'totalTreatments' => $totalTreatments,
                    'avgVolumeIn' => round($avgVolumeIn, 1),
                    'avgVolumeOut' => round($avgVolumeOut, 1),
                    'avgBalance' => round($avgBalance, 1),
                    'lastTreatmentDate' => $lastTreatment ? $lastTreatment->treatmentDate : null
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch treatment stats for patient ' . $patientId . ': ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch treatment stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}