<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckupController extends Controller
{
    /**
     * Complete a patient checkup
     */
    public function completeCheckup(Request $request, $schedule_id)
    {
        DB::beginTransaction();
        
        try {
            $schedule = Schedule::findOrFail($schedule_id);
            
            // Update current appointment
            $schedule->update([
                'checkup_status' => 'completed',
                'remarks' => 'Completed',
                'updated_at' => now()
            ]);
            
            // Calculate next appointment (exactly 28 days from new_appointment_date)
            $nextAppointmentDate = Schedule::calculateNextAppointmentDate($schedule->new_appointment_date);
            
            // Create new appointment
            Schedule::create([
                'userID' => $schedule->userID,
                'patient_id' => $schedule->patient_id,
                'appointment_date' => $schedule->new_appointment_date,
                'new_appointment_date' => $nextAppointmentDate,
                'confirmation_status' => 'pending',
                'checkup_status' => 'pending',
                'remarks' => 'Regular check-up'
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Checkup completed successfully',
                'next_appointment' => $nextAppointmentDate
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete checkup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check for missed appointments (to be run daily via cron job)
     */
    public function processMissedAppointments()
    {
        $today = Carbon::today()->format('Y-m-d');
        
        $missedAppointments = Schedule::where('appointment_date', '<', $today)
                                    ->where('checkup_status', 'pending')
                                    ->get();
        
        foreach ($missedAppointments as $appointment) {
            // Update current appointment as missed
            $appointment->update([
                'checkup_status' => 'missed',
                'remarks' => 'Missed appointment'
            ]);
            
            // Calculate next appointment (exactly 28 days from new_appointment_date)
            $nextAppointmentDate = Schedule::calculateNextAppointmentDate($appointment->new_appointment_date);
            
            // Create new appointment
            Schedule::create([
                'userID' => $appointment->userID,
                'patient_id' => $appointment->patient_id,
                'appointment_date' => $appointment->new_appointment_date,
                'new_appointment_date' => $nextAppointmentDate,
                'confirmation_status' => 'pending',
                'checkup_status' => 'pending',
                'remarks' => 'Pending'
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Processed ' . count($missedAppointments) . ' missed appointments'
        ]);
    }
}