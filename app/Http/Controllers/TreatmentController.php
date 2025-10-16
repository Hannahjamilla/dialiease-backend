<?php

namespace App\Http\Controllers;

use App\Models\Insolution;
use App\Models\Outsolution;
use App\Models\Patient;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Schedule;

class TreatmentController extends Controller
{
    public function startTreatment(Request $request)
{
    $validator = Validator::make($request->all(), [
        'volume' => 'required|numeric|min:1000|max:2000',
        'startTime' => 'required|date', // More flexible date validation
        'finishedTime' => 'required|date|after:startTime',
        'dwellTime' => 'required|in:4,6,8',
        'dialysate' => 'required|in:1.5,2.5,4.25',
        'bagSerialNumber' => 'required|string|max:255'
    ], [
        'startTime.date' => 'Invalid start time format',
        'finishedTime.date' => 'Invalid finish time format',
        'finishedTime.after' => 'Finish time must be after start time'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    DB::beginTransaction();
    try {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $patient = Patient::where('userID', $user->userID)->first();
        if (!$patient) {
            return response()->json(['success' => false, 'message' => 'Patient not found'], 404);
        }

        // Convert dates to proper format
        $startTime = Carbon::parse($request->startTime);
        $finishedTime = Carbon::parse($request->finishedTime);

        $inSolution = Insolution::create([
            'InStarted' => $startTime,
            'InFinished' => $finishedTime,
            'VolumeIn' => $request->volume,
            'Dialysate' => $request->dialysate,
            'Dwell' => $request->dwellTime,
            'patientID' => $patient->patientID
        ]);

        $treatment = Treatment::create([
            'patientID' => $patient->patientID,
            'IN_ID' => $inSolution->IN_ID,
            'TreatmentStatus' => 'Ongoing',
            'treatmentDate' => now(),
            'bagSerialNumber' => $request->bagSerialNumber,
            'dry_night' => $request->dwellTime == '6'
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Treatment started',
            'data' => [
                'treatment_id' => $treatment->Treatment_ID,
                'start_time' => $startTime->toDateTimeString(),
                'end_time' => $finishedTime->toDateTimeString()
            ]
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Treatment start error: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Server error',
            'error' => $e->getMessage()
        ], 500);
    }
}


    public function getOngoingTreatment(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $patient = Patient::where('userID', $user->userID)->first();
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $treatment = Treatment::with(['inSolution', 'outSolution'])
                ->where('patientID', $patient->patientID)
                ->where('TreatmentStatus', 'Ongoing')
                ->first();

            return response()->json([
                'success' => true,
                'has_ongoing' => !is_null($treatment),
                'data' => $treatment ? $this->formatTreatmentData($treatment) : null
            ]);

        } catch (\Exception $e) {
            Log::error('Get ongoing treatment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check ongoing treatment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function completeTreatment(Request $request, $id)
    {
        try {
            $treatment = Treatment::with(['inSolution', 'outSolution'])
                ->findOrFail($id);

            if ($treatment->TreatmentStatus !== 'Ongoing') {
                return response()->json([
                    'success' => false,
                    'message' => 'This treatment is not ongoing'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'drainFinished' => 'required|date',
                'volumeOut' => 'required|numeric|min:0',
                'color' => 'required|string|max:50',
                'notes' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            return DB::transaction(function () use ($request, $treatment) {
                $outSolution = Outsolution::create([
                    'DrainStarted' => $treatment->inSolution->InFinished,
                    'DrainFinished' => $request->drainFinished,
                    'VolumeOut' => $request->volumeOut,
                    'Color' => $request->color,
                    'Notes' => $request->notes,
                    'patientID' => $treatment->patientID
                ]);

                $balance = $treatment->inSolution->VolumeIn - $request->volumeOut;

                $treatment->update([
                    'TreatmentStatus' => 'Completed',
                    'Balances' => $balance,
                    'out_solution_id' => $outSolution->OUT_ID
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Treatment completed successfully',
                    'data' => $this->formatTreatmentData($treatment->fresh())
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Complete treatment error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete treatment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getTodayTreatmentCount(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $patient = Patient::where('userID', $user->userID)->first();
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $count = Treatment::where('patientID', $patient->patientID)
                ->whereDate('treatmentDate', Carbon::today())
                ->count();

            return response()->json([
                'success' => true,
                'count' => $count,
                'limit_reached' => $count >= 3
            ]);

        } catch (\Exception $e) {
            Log::error('Get treatment count error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to get treatment count',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getPatientTreatments(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $patient = Patient::where('userID', $user->userID)->first();
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $treatments = Treatment::where('patientID', $patient->patientID)
                ->with(['inSolution', 'outSolution'])
                ->orderBy('treatmentDate', 'desc')
                ->get()
                ->map(function ($treatment) {
                    return $this->formatTreatmentData($treatment);
                });

            return response()->json([
                'success' => true,
                'data' => $treatments
            ]);

        } catch (\Exception $e) {
            Log::error('Get patient treatments error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve treatments',
                'error' => $e->getMessage()
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
                ->where('appointment_date', '>=', now())
                ->orderBy('appointment_date', 'asc')
                ->get();
                
            return response()->json([
                'success' => true,
                'appointments' => $appointments->map(function($appointment) {
                    return [
                        'id' => $appointment->schedule_id,
                        'date' => $appointment->appointment_date,
                        'status' => $appointment->confirmation_status,
                        'purpose' => $appointment->remarks,
                        'location' => 'Main Dialysis Center',
                        'doctor' => 'Nephrologist'
                    ];
                })
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve appointments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    protected function formatTreatmentData($treatment)
    {
        if (!$treatment) return null;

        $inSolution = $treatment->inSolution;
        $outSolution = $treatment->outSolution;

        return [
            'treatment_id' => $treatment->Treatment_ID,
            'status' => $treatment->TreatmentStatus,
            'date' => $treatment->treatmentDate,
            'bag_serial_number' => $treatment->bagSerialNumber,
            'dry_night' => (bool)$treatment->dry_night,
            'in_solution' => $inSolution ? [
                'id' => $inSolution->IN_ID,
                'started_at' => $inSolution->InStarted,
                'finished_at' => $inSolution->InFinished,
                'volume' => $inSolution->VolumeIn,
                'dialysate' => $inSolution->Dialysate,
                'dwell_time' => $inSolution->Dwell
            ] : null,
            'out_solution' => $outSolution ? [
                'id' => $outSolution->OUT_ID,
                'drain_started' => $outSolution->DrainStarted,
                'drain_finished' => $outSolution->DrainFinished,
                'volume' => $outSolution->VolumeOut,
                'color' => $outSolution->Color,
                'notes' => $outSolution->Notes
            ] : null,
            'balance' => $treatment->Balances,
            'duration' => $inSolution && $inSolution->InFinished && $outSolution && $outSolution->DrainFinished
                ? Carbon::parse($inSolution->InFinished)->diffInHours(Carbon::parse($outSolution->DrainFinished))
                : null
        ];
    }
}