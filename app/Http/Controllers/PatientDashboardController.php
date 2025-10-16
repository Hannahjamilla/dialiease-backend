<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Treatment;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class PatientDashboardController extends Controller
{
    public function getOngoingTreatment(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user || $user->userLevel !== 'patient') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 401);
            }
            
            $patient = Patient::where('userID', $user->userID)->first();
            
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }
            
            $treatment = Treatment::where('patientID', $patient->patientID)
                ->where(function($query) {
                    $query->where('TreatmentStatus', 'In Progress')
                          ->orWhere('TreatmentStatus', 'Draining')
                          ->orWhere('TreatmentStatus', 'Ongoing');
                })
                ->with(['inSolution', 'outSolution'])
                ->orderBy('treatmentDate', 'desc')
                ->first();

            return response()->json([
                'success' => true,
                'has_ongoing' => !is_null($treatment),
                'treatment' => $treatment ? $this->formatTreatmentData($treatment) : null
            ]);
            
        } catch (\Exception $e) {
            report($e); // Log the exception
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ongoing treatment',
                'error' => 'Server error'
            ], 500);
        }
    }

    public function getRecentTreatments(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'limit' => 'sometimes|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid input',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            
            if (!$user || $user->userLevel !== 'patient') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 401);
            }
            
            $patient = Patient::where('userID', $user->userID)->first();
            
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }
            
            $limit = $request->input('limit', 10);
            
            $treatments = Treatment::where('patientID', $patient->patientID)
                ->with(['inSolution', 'outSolution'])
                ->orderBy('treatmentDate', 'desc')
                ->take($limit)
                ->get()
                ->map(function($treatment) {
                    return $this->formatTreatmentData($treatment);
                });

            return response()->json([
                'success' => true,
                'count' => $treatments->count(),
                'treatments' => $treatments
            ]);
            
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve treatment history',
                'error' => 'Server error'
            ], 500);
        }
    }

    public function getUpcomingAppointments(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user || $user->userLevel !== 'patient') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 401);
            }

            $patient = Patient::where('userID', $user->userID)->first();

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $appointments = Schedule::where('patient_id', $patient->patientID)
                ->whereDate('appointment_date', '>=', now()->toDateString())
                ->whereDate('appointment_date', '<=', now()->addDays(30)->toDateString())
                ->where('confirmation_status', 'confirmed')
                ->orderBy('appointment_date', 'asc')
                ->orderBy('appointment_time', 'asc')
                ->get()
                ->map(function($appointment) {
                    return [
                        'id' => $appointment->queuing_ID,
                        'date' => $appointment->appointment_date . ' ' . $appointment->appointment_time,
                        'status' => $appointment->confirmation_status,
                        'purpose' => $appointment->remarks ?? 'Regular Checkup',
                        'notes' => $appointment->remarks,
                        'location' => $appointment->location ?? 'Main Clinic'
                    ];
                });

            return response()->json([
                'success' => true,
                'count' => $appointments->count(),
                'appointments' => $appointments
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve appointments',
                'error' => 'Server error'
            ], 500);
        }
    }

    protected function formatTreatmentData(Treatment $treatment)
    {
        $inSolution = $treatment->inSolution;
        $outSolution = $treatment->outSolution;
    
        $drainFinished = $outSolution ? $outSolution->DrainFinished : null;
        $inFinished = $inSolution ? $inSolution->InFinished : null;
        
        $duration = null;
        if ($inFinished && $drainFinished) {
            $start = Carbon::parse($inFinished);
            $end = Carbon::parse($drainFinished);
            $duration = [
                'hours' => $start->diffInHours($end),
                'minutes' => $start->diffInMinutes($end) % 60
            ];
        }
    
        return [
            'Treatment_ID' => $treatment->Treatment_ID,
            'TreatmentStatus' => $treatment->TreatmentStatus,
            'treatmentDate' => $treatment->treatmentDate,
            'bagSerialNumber' => $treatment->bagSerialNumber,
            'dry_night' => (bool)$treatment->dry_night,
            'treatmentType' => $treatment->treatmentType ?? 'Standard',
            'inSolution' => $inSolution ? [
                'InStarted' => $inSolution->InStarted,
                'InFinished' => $inFinished,
                'VolumeIn' => (float)$inSolution->VolumeIn,
                'Dialysate' => (float)$inSolution->Dialysate,
                'Dwell' => (float)$inSolution->Dwell,
                'treatmentType' => $inSolution->treatmentType ?? null
            ] : null,
            'outSolution' => $outSolution ? [
                'DrainStarted' => $outSolution->DrainStarted,
                'DrainFinished' => $drainFinished,
                'VolumeOut' => $outSolution->VolumeOut ? (float)$outSolution->VolumeOut : null,
                'Color' => $outSolution->Color ?? 'Clear',  // Ensure Color is included
                'Notes' => $outSolution->Notes ?? null      // Ensure Notes is included
            ] : null,
            'duration' => $duration,
            'created_at' => $treatment->created_at,
            'updated_at' => $treatment->updated_at
        ];
    }
}