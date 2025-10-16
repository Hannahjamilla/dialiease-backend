<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DoctorStatusController extends Controller
{
    public function getDoctorsStatus()
    {
        try {
            // Get all doctors with their today's schedules
            $doctors = User::where('userLevel', 'doctor')
                ->with(['schedules' => function($query) {
                    $query->where(function($q) {
                        $q->whereDate('appointment_date', Carbon::today())
                          ->orWhereDate('new_appointment_date', Carbon::today());
                    })
                    ->where('checkup_status', '!=', 'Completed')
                    ->orderBy('appointment_date', 'asc');
                }])
                ->get()
                ->map(function($doctor) {
                    // Determine doctor's current status
                    $status = 'Off Duty';
                    $currentPatient = null;
                    
                    // Check if doctor is on duty today
                    $isOnDuty = isset($doctor->TodaysStatus) && 
                               (strtolower($doctor->TodaysStatus) === 'in duty' || 
                                strtolower($doctor->TodaysStatus) === 'on duty');

                    if ($isOnDuty) {
                        $status = 'On Duty';
                        
                        // Find current patient if any
                        $currentSchedule = $doctor->schedules->first(function($schedule) {
                            return $schedule->checkup_status === 'In Progress';
                        });

                        if ($currentSchedule) {
                            $status = 'With Patient';
                            $currentPatient = [
                                'patient_id' => $currentSchedule->patient_id,
                                'schedule_id' => $currentSchedule->schedule_id,
                                'appointment_time' => $currentSchedule->appointment_date,
                            ];
                        }
                    }

                    return [
                        'doctor_id' => $doctor->userID,
                        'name' => "Dr. {$doctor->first_name} {$doctor->last_name}",
                        'status' => $status,
                        'current_patient' => $currentPatient,
                        'upcoming_appointments' => $doctor->schedules->where('checkup_status', 'Pending')->count(),
                    ];
                });

            return response()->json([
                'success' => true,
                'doctors' => $doctors,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch doctors status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch doctors status. Please try again later.'
            ], 500);
        }
    }
}