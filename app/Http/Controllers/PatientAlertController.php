<?php
// app/Http/Controllers/PatientAlertController.php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Models\DoctorAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PatientAlertController extends Controller
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
    
    // Update patient situation status to "AtEmergency"
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
            
            $patient->situationStatus = 'AtEmergency';
            $patient->save();
            
            // Create a record of this action
            DoctorAlert::create([
                'patientID' => $patient->patientID,
                'type' => 'emergency_confirmation',
                'message' => 'Patient confirmed going to emergency',
                'severity' => 'high'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Emergency status updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update emergency status: ' . $e->getMessage()
            ], 500);
        }
    }
}