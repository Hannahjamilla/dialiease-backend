<?php
// app/Http/Controllers/PatientAlertController.php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\DoctorAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    // Get doctor alerts for the authenticated patient
    public function getDoctorAlerts()
    {
        try {
            $patient = Patient::where('userID', Auth::id())->first();
            
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], 404);
            }
            
            $alerts = DoctorAlert::where('patientID', $patient->patientID)
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'success' => true,
                'alerts' => $alerts
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch alerts: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Get patient status
    public function getPatientStatus()
    {
        try {
            $patient = Patient::where('userID', Auth::id())->first();
            
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'status' => $patient->situationStatus
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch patient status: ' . $e->getMessage()
            ], 500);
        }
    }
    
    // Update patient situation status to "InEmergency"
    public function confirmEmergency()
    {
        try {
            $patient = Patient::where('userID', Auth::id())->first();
            
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], 404);
            }
            
            // Update patient situation status
            $patient->situationStatus = 'InEmergency';
            $patient->save();
            
            // Create a record of this confirmation
            DoctorAlert::create([
                'patientID' => $patient->patientID,
                'type' => 'emergency_confirmation',
                'message' => 'Patient confirmed going to emergency',
                'severity' => 'high',
                'status' => 'completed'
            ]);
            
            // Update any pending emergency recommendations
            DoctorAlert::where('patientID', $patient->patientID)
                ->where('type', 'emergency_recommendation')
                ->where('status', 'active')
                ->update(['status' => 'completed']);
            
            return response()->json([
                'success' => true,
                'message' => 'Emergency status confirmed successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm emergency: ' . $e->getMessage()
            ], 500);
        }
    }
}