<?php

namespace App\Http\Controllers;

use App\Models\Treatments;
use App\Models\Insolution;
use App\Models\Outsolution;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class Treatments_Controller extends Controller
{
    public function getOngoingTreatment(Request $request)
    {
        $user = Auth::user();
        
        $patient = DB::table('patients')->where('userID', $user->userID)->first();
        
        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient record not found'
            ], 404);
        }

        $treatment = Treatments::with('inSolution')
            ->where('patientID', $patient->patientID)
            ->where('TreatmentStatus', 'Ongoing')
            ->first();
        
        if ($treatment) {
            return response()->json([
                'success' => true,
                'has_ongoing' => true,
                'treatment' => $treatment,
                'message' => 'Ongoing treatment found'
            ]);
        }
        
        return response()->json([
            'success' => true,
            'has_ongoing' => false,
            'message' => 'No ongoing treatment found'
        ]);
    }

   public function startTreatment(Request $request)
{
    DB::beginTransaction();

    try {
        $validator = Validator::make($request->all(), [
            'volume' => 'required|numeric|min:1.0|max:2.0',
            'volume_kl' => 'required|numeric',
            'startTime' => 'required|date',
            'finishedTime' => 'required|date',
            'dwellTime' => 'required|numeric|min:15',
            'dialysate' => 'required|in:1.5,2.5,4.25',
            'bagSerialNumber' => 'required|string|max:50',
            'dry_night' => 'required|in:yes,no',
            'device_connected' => 'required|boolean',
            'final_weight' => 'nullable|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $patient = DB::table('patients')->where('userID', $user->userID)->first();

        if (!$patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient record not found'
            ], 404);
        }

        // Check for ongoing treatment
        $ongoing = Treatments::where('patientID', $patient->patientID)
            ->where('TreatmentStatus', 'Ongoing')
            ->exists();

        if ($ongoing) {
            return response()->json([
                'success' => false,
                'message' => 'Patient already has an ongoing treatment'
            ], 400);
        }

        // Create in solution
        $inSolution = Insolution::create([
            'VolumeIn' => $request->volume,
            'VolumeInKl' => $request->volume_kl,
            'InStarted' => $request->startTime,
            'InFinished' => $request->finishedTime,
            'Dwell' => $request->dwellTime,
            'Dialysate' => $request->dialysate,
            'DeviceWeight' => $request->final_weight,
            'DeviceConnected' => $request->device_connected,
            'Notes' => $request->device_connected ? 
                "Automatically recorded by IoT device" : 
                "Manually entered by patient"
        ]);

        // Create treatment
        $treatment = Treatments::create([
            'patientID' => $patient->patientID,
            'IN_ID' => $inSolution->IN_ID,
            'treatmentDate' => Carbon::now(),
            'TreatmentStatus' => 'Ongoing',
            'bagSerialNumber' => $request->bagSerialNumber,
            'dry_night' => $request->dry_night,
            'Reminders' => json_encode([
                'next_check' => Carbon::now()->addMinutes(30)->toDateTimeString(),
                'completed_steps' => []
            ])
        ]);

        // Update IoT cache
        Cache::put('iot_treatment_status', 'active', now()->addHours(1));
        Cache::put('iot_treatment_started_at', now()->toDateTimeString(), now()->addHours(1));

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Treatment started successfully',
            'treatment_id' => $treatment->Treatment_ID,
            'volume_recorded' => $request->volume,
            'volume_kl' => $request->volume_kl,
            'device_used' => $request->device_connected
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Failed to start treatment',
            'error' => $e->getMessage()
        ], 500);
    }
}

      public function endTreatment(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'treatmentId' => 'required|exists:treatment,Treatment_ID',
                'drainFinished' => 'required|date',
                'volumeOut' => 'required|numeric|min:1000|max:2500',
                'color' => 'required|in:Clear,Cloudy,Reddish',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $patient = DB::table('patients')->where('userID', $user->userID)->first();

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            // Get the treatment with all necessary checks
            $treatment = Treatments::with('inSolution')
                ->where('Treatment_ID', $request->treatmentId)
                ->where('patientID', $patient->patientID)
                ->where('TreatmentStatus', 'Ongoing')
                ->first();

            if (!$treatment) {
                return response()->json([
                    'success' => false,
                    'message' => 'No ongoing treatment found with the provided ID for this patient'
                ], 404);
            }

            // Create out solution
            $outSolution = Outsolution::create([
                'DrainStarted' => Carbon::parse($request->drainFinished)->subMinutes(30),
                'DrainFinished' => $request->drainFinished,
                'VolumeOut' => $request->volumeOut,
                'Color' => $request->color,
                'Notes' => $request->notes
            ]);

            // Calculate balance
            $balance = $treatment->inSolution->VolumeIn - $request->volumeOut;

            // Update treatment
            $treatment->OUT_ID = $outSolution->OUT_ID;
            $treatment->TreatmentStatus = 'Completed';
            $treatment->Balances = $balance;
            $treatment->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Treatment completed successfully',
                'Balances' => $balance,
                'volumeIn' => $treatment->inSolution->VolumeIn,
                'volumeOut' => $request->volumeOut,
                'treatment_id' => $treatment->Treatment_ID
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Failed to complete treatment', [
                'error' => $e->getMessage(),
                'stack' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete treatment',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function getTreatmentHistory(Request $request)
    {
        try {
            $user = Auth::user();
            $patient = DB::table('patients')->where('userID', $user->userID)->first();

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $treatments = Treatments::with(['inSolution', 'outSolution'])
                ->where('patientID', $patient->patientID)
                ->orderBy('treatmentDate', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'treatments' => $treatments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch treatment history: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getTreatmentDetails($id)
    {
        try {
            $user = Auth::user();
            $patient = DB::table('patients')->where('userID', $user->userID)->first();

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $treatment = Treatments::with(['inSolution', 'outSolution'])
                ->where('Treatment_ID', $id)
                ->where('patientID', $patient->patientID)
                ->first();

            if (!$treatment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Treatment not found'
                ], 404);
            }

            $details = [
                'id' => $treatment->Treatment_ID,
                'date' => $treatment->treatmentDate,
                'status' => $treatment->TreatmentStatus,
                'bagSerialNumber' => $treatment->bagSerialNumber,
                'balance' => $treatment->Balances,
                'balance_kl' => $treatment->BalancesKl,
                'volumeIn' => $treatment->inSolution ? $treatment->inSolution->VolumeIn : null,
                'volumeInKl' => $treatment->inSolution ? $treatment->inSolution->VolumeInKl : null,
                'volumeOut' => $treatment->outSolution ? $treatment->outSolution->VolumeOut : null,
                'volumeOutKl' => $treatment->outSolution ? $treatment->outSolution->VolumeOutKl : null,
                'dialysate' => $treatment->inSolution ? $treatment->inSolution->Dialysate : null,
                'dwellTime' => $treatment->inSolution ? $treatment->inSolution->Dwell : null,
                'color' => $treatment->outSolution ? $treatment->outSolution->Color : null,
                'notes' => $treatment->outSolution ? $treatment->outSolution->Notes : null,
                'patientNotes' => $treatment->outSolution ? $treatment->outSolution->PatientNotes : null,
                'startTime' => $treatment->inSolution ? $treatment->inSolution->InStarted : null,
                'finishedTime' => $treatment->inSolution ? $treatment->inSolution->InFinished : null,
                'drainStarted' => $treatment->outSolution ? $treatment->outSolution->DrainStarted : null,
                'drainFinished' => $treatment->outSolution ? $treatment->outSolution->DrainFinished : null,
                'deviceWeight' => $treatment->inSolution ? $treatment->inSolution->DeviceWeight : null,
                'deviceConnected' => $treatment->inSolution ? $treatment->inSolution->DeviceConnected : null,
                'deviceNotes' => $treatment->inSolution ? $treatment->inSolution->Notes : null,
                'reminders' => $treatment->Reminders ? json_decode($treatment->Reminders, true) : null
            ];

            return response()->json([
                'success' => true,
                'treatment' => $details
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch treatment details: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addTreatmentNote(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'note' => 'required|string|max:1000',
                'note_type' => 'required|in:patient,medical'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $patient = DB::table('patients')->where('userID', $user->userID)->first();

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $treatment = Treatments::with('outSolution')
                ->where('Treatment_ID', $id)
                ->where('patientID', $patient->patientID)
                ->first();

            if (!$treatment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Treatment not found'
                ], 404);
            }

            if ($request->note_type === 'patient') {
                $treatment->outSolution->PatientNotes = $request->note;
                $treatment->outSolution->save();
            } else {
                $treatment->outSolution->Notes = $request->note;
                $treatment->outSolution->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Note added successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add note: ' . $e->getMessage()
            ], 500);
        }
    }
}