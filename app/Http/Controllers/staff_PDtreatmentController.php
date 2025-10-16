<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\Treatment;
use App\Models\Insolution;
use App\Models\Outsolution;
use App\Models\User;
use Illuminate\Http\Request;

class staff_PDtreatmentController extends Controller
{
    public function getAllTreatments()
    {
        try {
            // Eager load all necessary relationships with proper relationship names
            $treatments = Treatment::with([
                'patient.user', // Make sure these relationship names match your model definitions
                'insolution', 
                'outsolution'
            ])->orderBy('treatmentDate', 'desc')->get();

            // Format the response data
            $formattedTreatments = $treatments->map(function ($treatment) {
                $patient = $treatment->patient;
                $user = $patient ? $patient->user : null;
                
                // Handle cases where patient or user might be null
                $patientName = 'Unknown';
                $hospitalNumber = 'N/A';
                
                if ($patient) {
                    $hospitalNumber = $patient->hospitalNumber ?? 'N/A';
                    if ($user) {
                        $firstName = $user->first_name ?? '';
                        $lastName = $user->last_name ?? '';
                        $patientName = trim("$firstName $lastName");
                        if (empty($patientName)) {
                            $patientName = 'Unknown';
                        }
                    }
                }

                return [
                    'treatment_id' => $treatment->Treatment_ID,
                    'patient_id' => $treatment->patientID,
                    'patient_name' => $patientName,
                    'hospital_number' => $hospitalNumber,
                    'treatment_date' => $treatment->treatmentDate,
                    'treatment_status' => $treatment->TreatmentStatus,
                    'balances' => $treatment->Balances,
                    'bag_serial_number' => $treatment->bagSerialNumber,
                    'in_solution' => $treatment->insolution ? [
                        'in_started' => $treatment->insolution->InStarted,
                        'in_finished' => $treatment->insolution->InFinished,
                        'volume_in' => $treatment->insolution->VolumeIn,
                        'dialysate' => $treatment->insolution->Dialysate,
                        'dwell' => $treatment->insolution->Dwell,
                    ] : null,
                    'out_solution' => $treatment->outsolution ? [
                        'drain_started' => $treatment->outsolution->DrainStarted,
                        'drain_finished' => $treatment->outsolution->DrainFinished,
                        'volume_out' => $treatment->outsolution->VolumeOut,
                        'color' => $treatment->outsolution->Color,
                        'notes' => $treatment->outsolution->Notes,
                    ] : null,
                    'created_at' => $treatment->created_at,
                    'updated_at' => $treatment->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'treatments' => $formattedTreatments,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch treatments',
                'error' => $e->getMessage() // Only include in development
            ], 500);
        }
    }

    public function getPatientTreatments($patientId)
    {
        try {
            // Verify patient exists with user relationship
            $patient = Patient::with('user')->find($patientId);
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found',
                ], 404);
            }

            // Handle patient name construction
            $patientName = 'Unknown';
            if ($patient->user) {
                $firstName = $patient->user->first_name ?? '';
                $lastName = $patient->user->last_name ?? '';
                $patientName = trim("$firstName $lastName");
                if (empty($patientName)) {
                    $patientName = 'Unknown';
                }
            }

            $hospitalNumber = $patient->hospitalNumber ?? 'N/A';

            // Get treatments for specific patient
            $treatments = Treatment::with(['insolution', 'outsolution'])
                ->where('patientID', $patientId)
                ->orderBy('treatmentDate', 'desc')
                ->get();

            // Format the data for response
            $formattedTreatments = $treatments->map(function ($treatment) use ($patient, $patientName, $hospitalNumber) {
                return [
                    'treatment_id' => $treatment->Treatment_ID,
                    'patient_id' => $treatment->patientID,
                    'patient_name' => $patientName,
                    'hospital_number' => $hospitalNumber,
                    'treatment_date' => $treatment->treatmentDate,
                    'treatment_status' => $treatment->TreatmentStatus,
                    'balances' => $treatment->Balances,
                    'bag_serial_number' => $treatment->bagSerialNumber,
                    'in_solution' => $treatment->insolution ? [
                        'in_started' => $treatment->insolution->InStarted,
                        'in_finished' => $treatment->insolution->InFinished,
                        'volume_in' => $treatment->insolution->VolumeIn,
                        'dialysate' => $treatment->insolution->Dialysate,
                        'dwell' => $treatment->insolution->Dwell,
                    ] : null,
                    'out_solution' => $treatment->outsolution ? [
                        'drain_started' => $treatment->outsolution->DrainStarted,
                        'drain_finished' => $treatment->outsolution->DrainFinished,
                        'volume_out' => $treatment->outsolution->VolumeOut,
                        'color' => $treatment->outsolution->Color,
                        'notes' => $treatment->outsolution->Notes,
                    ] : null,
                    'created_at' => $treatment->created_at,
                    'updated_at' => $treatment->updated_at,
                ];
            });

            return response()->json([
                'success' => true,
                'patient' => [
                    'id' => $patient->patientID,
                    'name' => $patientName,
                    'hospital_number' => $hospitalNumber,
                ],
                'treatments' => $formattedTreatments,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch patient treatments: ' . $e->getMessage(),
            ], 500);
        }
    }
}