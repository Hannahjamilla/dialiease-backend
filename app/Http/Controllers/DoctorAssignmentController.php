<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Queue;
use App\Models\User;
use App\Models\Patient;

class DoctorAssignmentController extends Controller
{
    /**
     * Get all patients assigned to the currently authenticated doctor
     */
    public function getAssignedPatients(Request $request)
    {
        $doctor = auth()->user();
        
        if (!$doctor || $doctor->userLevel !== 'doctor') {
            return response()->json(['error' => 'Unauthorized. Doctor access only.'], 403);
        }

        $today = Carbon::today()->format('Y-m-d');
        
        // Get all patients assigned to this doctor for today, excluding completed checkups
        $assignedPatients = Queue::with(['user', 'patient'])
            ->whereDate('appointment_date', $today)
            ->where('doctor_id', $doctor->userID)
            ->where('checkup_status', '!=', 'Completed') // Exclude completed checkups
            ->whereIn('status', ['in-progress', 'waiting'])
            ->orderBy('emergency_status', 'desc')
            ->orderBy('emergency_priority', 'desc')
            ->orderBy('queue_number', 'asc')
            ->get()
            ->map(function ($queue) {
                // Get the actual patient record to get the correct patientID
                $patient = Patient::where('userID', $queue->userID)->first();
                
                return [
                    'queue_id' => $queue->queue_id,
                    'queue_number' => $queue->queue_number,
                    'patient_id' => $patient ? $patient->patientID : null, // CORRECT: patientID from patients table
                    'user_id' => $queue->userID, // userID from users table
                    'patient_name' => $queue->user ? $queue->user->first_name . ' ' . $queue->user->last_name : 'Unknown',
                    'hospital_number' => $patient ? $patient->hospitalNumber : 'N/A',
                    'status' => $queue->status,
                    'emergency_status' => (bool)$queue->emergency_status,
                    'emergency_priority' => $queue->emergency_priority,
                    'start_time' => $queue->start_time,
                    'appointment_date' => $queue->appointment_date,
                    'doctor_id' => $queue->doctor_id,
                    'checkup_status' => $queue->checkup_status
                ];
            });

        return response()->json([
            'patients' => $assignedPatients,
            'doctor' => [
                'doctor_id' => $doctor->userID,
                'doctor_name' => 'Dr. ' . $doctor->first_name . ' ' . $doctor->last_name,
                'specialization' => $doctor->specialization
            ],
            'current_date' => $today
        ]);
    }
}