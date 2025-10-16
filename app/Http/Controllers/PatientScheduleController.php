<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Mail\AppointmentRescheduled;


use Illuminate\Support\Facades\Mail;


class PatientScheduleController extends Controller
{
    public function upcomingCheckups(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            if (!$user->patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found for this user'
                ], 404);
            }

            $checkups = Schedule::with(['patient.user'])
                ->where('patient_id', $user->patient->patientID)
                ->orderBy('appointment_date', 'asc')
                ->get()
                ->map(function ($checkup) {
                    $now = Carbon::now();
                    $appointmentDate = Carbon::parse($checkup->appointment_date);
                    $daysDiff = $now->diffInDays($appointmentDate, false);
                    
                    $reminderStatus = null;
                    if ($daysDiff === 0) {
                        $reminderStatus = 'today';
                    } elseif ($daysDiff === 1) {
                        $reminderStatus = 'tomorrow';
                    } elseif ($daysDiff <= 7) {
                        $reminderStatus = 'in_1_week';
                    }
                    
                    return [
                        'schedule_id' => $checkup->schedule_id,
                        'appointment_date' => $checkup->appointment_date,
                        'confirmation_status' => $checkup->confirmation_status ?? 'pending',
                        'checkup_status' => $checkup->checkup_status ?? 'pending',
                        'reminder_status' => $reminderStatus,
                        'is_past' => $daysDiff < 0,
                        'remarks' => $checkup->remarks,
                        'first_name' => $checkup->patient->user->first_name ?? '',
                        'last_name' => $checkup->patient->user->last_name ?? '',
                    ];
                });

            return response()->json([
                'success' => true,
                'checkups' => $checkups,
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch checkups',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function confirmationStatus(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $today = Carbon::today()->format('Y-m-d');
            
            $todayCheckup = Schedule::where('patient_id', $user->patient->patientID)
                ->whereDate('appointment_date', $today)
                ->first();

            $requiresConfirmation = false;
            $appointmentDate = null;
            
            if ($todayCheckup && $todayCheckup->confirmation_status === 'pending') {
                $requiresConfirmation = true;
                $appointmentDate = $todayCheckup->appointment_date;
            }

            return response()->json([
                'success' => true,
                'requiresConfirmation' => $requiresConfirmation,
                'appointmentDate' => $appointmentDate
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check confirmation status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

public function requestReschedule(Request $request)
{
    try {
        $user = Auth::user();
        
        if (!$user || !$user->patient) {
            return response()->json([
                'success' => false,
                'message' => 'Patient record not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'schedule_id' => 'required|exists:schedule,schedule_id',
            'reason' => 'required|string|max:255'
        ], [
            'reason.required' => 'Please provide a reason for rescheduling',
            'reason.max' => 'Reason should not exceed 255 characters'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $schedule = Schedule::where('schedule_id', $request->schedule_id)
            ->where('patient_id', $user->patient->patientID)
            ->first();

        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule not found or not authorized'
            ], 404);
        }

        // Create reschedule request (without new date)
        $schedule->update([
            'reschedule_reason' => $request->reason,
            'reschedule_status' => 'pending',
            'confirmation_status' => 'pending' // Reset confirmation status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reschedule request submitted successfully. Waiting for admin approval.'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to request reschedule',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function processReschedule(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'schedule_id' => 'required|exists:schedule,schedule_id',
            'new_date' => 'required|date|after:today',
            'email_content' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $schedule = Schedule::find($request->schedule_id);
        
        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule not found'
            ], 404);
        }

        // Update the schedule
        $schedule->update([
            'appointment_date' => $request->new_date,
            'reschedule_status' => 'approved',
            'confirmation_status' => 'confirmed',
            'reschedule_processed_at' => now(),
            'reschedule_processed_by' => Auth::id()
        ]);

        // Get patient email
        $patientEmail = $schedule->patient->user->email;

        // Send email
        Mail::to($patientEmail)->send(new AppointmentRescheduled(
            $schedule,
            $request->email_content
        ));

        return response()->json([
            'success' => true,
            'message' => 'Reschedule processed successfully and email sent to patient'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to process reschedule',
            'error' => $e->getMessage()
        ], 500);
    }
}
    public function dailyLimitStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date|after_or_equal:today'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid date format or date is in the past'
                ], 400);
            }

            $date = Carbon::parse($request->date)->format('Y-m-d');
            
            $count = Schedule::whereDate('appointment_date', $date)
                ->where('confirmation_status', 'confirmed')
                ->count();

            $dailyLimit = config('app.daily_patient_limit', 80);
            $limitReached = $count >= $dailyLimit;

            return response()->json([
                'success' => true,
                'count' => $count,
                'limit' => $dailyLimit,
                'limitReached' => $limitReached
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check daily limit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function confirmAppointment(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user || !$user->patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $validated = $request->validate([
                'schedule_id' => 'required|exists:schedule,schedule_id'
            ]);

            $schedule = Schedule::where('schedule_id', $validated['schedule_id'])
                ->where('patient_id', $user->patient->patientID)
                ->first();

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Schedule not found or not authorized'
                ], 404);
            }

            $appointmentDate = Carbon::parse($schedule->appointment_date);
            $now = Carbon::now();
            $daysDiff = $now->diffInDays($appointmentDate, false);
            
            if ($daysDiff < 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot confirm past appointments'
                ], 400);
            }

            if ($daysDiff > 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only confirm appointments within 2 days of the appointment date'
                ], 400);
            }

            if ($schedule->confirmation_status === 'confirmed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment already confirmed'
                ], 400);
            }

            $schedule->update([
                'confirmation_status' => 'confirmed',
                'confirmed_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Appointment confirmed successfully',
                'schedule' => $schedule
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm appointment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}