<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Mail\AppointmentRescheduled;
use App\Mail\CheckupCompleted;

class StaffDashboardController extends Controller
{
    public function getDashboardData()
    {
        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $now = Carbon::now();
        $currentDate = $now->format('Y-m-d');
        $yesterday = $now->copy()->subDay()->format('Y-m-d');
        $tomorrow = $now->copy()->addDay()->format('Y-m-d');
        $nextWeek = $now->copy()->addDays(7)->format('Y-m-d');
        
        // All schedules for the staff member
        $allSchedules = DB::table('patients')
            ->join('users', 'patients.userID', '=', 'users.userID')
            ->join('schedule', 'patients.patientID', '=', 'schedule.patient_id')
            ->select(
                'patients.patientID',
                'users.first_name',
                'users.last_name',
                'patients.hospitalNumber',
                'schedule.checkup_status',
                'schedule.appointment_date',
                'users.date_of_birth',
                'schedule.schedule_id',
                'schedule.confirmation_status',
                'schedule.reschedule_requested_date',
                'schedule.reschedule_reason',
                'schedule.checkup_remarks',
                'schedule.new_appointment_date',
                'schedule.missed_count'
            )
            ->orderBy('schedule.appointment_date', 'asc')
            ->get();

        // Today's scheduled patients
        $patientsToday = $allSchedules->filter(function ($schedule) use ($currentDate) {
            return $schedule->appointment_date && 
                   $this->parseAppointmentDate($schedule->appointment_date)->format('Y-m-d') === $currentDate;
        })->values();

        // Tomorrow's scheduled patients
        $patientsTomorrow = $allSchedules->filter(function ($schedule) use ($tomorrow) {
            return $schedule->appointment_date && 
                   $this->parseAppointmentDate($schedule->appointment_date)->format('Y-m-d') === $tomorrow;
        })->values();

        // Next week's patients
        $nextWeekPatients = $allSchedules->filter(function ($schedule) use ($currentDate, $nextWeek) {
            if (!$schedule->appointment_date) return false;
            $appointmentDate = $this->parseAppointmentDate($schedule->appointment_date);
            return $appointmentDate->between($currentDate, $nextWeek);
        })->values();

        // Confirmed patients (all upcoming)
        $confirmedPatients = $allSchedules->filter(function ($schedule) use ($currentDate) {
            return $schedule->confirmation_status === 'confirmed' && 
                   $this->parseAppointmentDate($schedule->appointment_date)->gte($currentDate);
        })->values();

        // Pending reschedule requests
        $rescheduledPatients = $allSchedules->filter(function ($schedule) {
            return $schedule->confirmation_status === 'pending_reschedule';
        })->values();

        // Upcoming appointments (next 7 days)
        $upcomingAppointments = $allSchedules->filter(function ($schedule) use ($currentDate, $nextWeek) {
            if (!$schedule->appointment_date) return false;
            $appointmentDate = $this->parseAppointmentDate($schedule->appointment_date);
            return $appointmentDate->between($currentDate, $nextWeek) &&
                   $schedule->confirmation_status === 'confirmed';
        })->values();

        // Get all unrescheduled missed appointments (past appointments with Pending status)
        $unrescheduledMissed = $allSchedules->filter(function ($schedule) use ($currentDate) {
            if (!$schedule->appointment_date) return false;
            
            $appointmentDate = $this->parseAppointmentDate($schedule->appointment_date);
            $isPast = $appointmentDate->lt($currentDate);
            $isPending = strtolower($schedule->checkup_status) === 'pending';
            $hasNoRemarks = empty($schedule->checkup_remarks) || 
                           $schedule->checkup_remarks === 'pending' ||
                           str_contains($schedule->checkup_remarks, 'Manually rescheduled from missed appointment');
            
            return $isPast && $isPending && $hasNoRemarks;
        })->values();

        // Get yesterday's specifically for the notification
        $yesterdaysUnrescheduled = $unrescheduledMissed->filter(function ($schedule) use ($yesterday) {
            return $this->parseAppointmentDate($schedule->appointment_date)->format('Y-m-d') === $yesterday;
        })->count();

        // Older missed appointments (before yesterday)
        $olderMissedCount = $unrescheduledMissed->count() - $yesterdaysUnrescheduled;

        // Patient statistics (last 6 months)
        $patientStats = DB::table('schedule')
            ->select(
                DB::raw('DATE_FORMAT(appointment_date, "%Y-%m") as month'),
                DB::raw('COUNT(*) as count')
            )
            ->where('appointment_date', '>=', $now->copy()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        // Counts for different statuses
        $counts = [
            'pending' => $allSchedules->whereIn('checkup_status', ['Pending', 'pending'])->count(),
            'completed' => $allSchedules->where('checkup_status', 'Completed')->count(),
            'rescheduled' => $rescheduledPatients->count(),
            'unrescheduled' => $unrescheduledMissed->count(),
            'yesterday_unrescheduled' => $yesterdaysUnrescheduled,
            'older_unrescheduled' => $olderMissedCount
        ];

        return response()->json([
            'allSchedules' => $allSchedules,
            'patientsToday' => $patientsToday,
            'patientsTomorrow' => $patientsTomorrow,
            'nextWeekPatients' => $nextWeekPatients,
            'confirmedPatients' => $confirmedPatients,
            'rescheduledPatients' => $rescheduledPatients,
            'upcomingAppointments' => $upcomingAppointments,
            'patientStats' => $patientStats,
            'counts' => $counts,
            'currentDate' => $currentDate,
            'unrescheduledMissed' => $unrescheduledMissed,
            'yesterdaysUnrescheduled' => $yesterdaysUnrescheduled,
        ]);
    }

    public function rescheduleMissedBatch(Request $request)
    {
        $validated = $request->validate([
            'schedule_ids' => 'required|array',
            'schedule_ids.*' => 'integer'
        ]);

        $successCount = 0;
        $errors = [];
        $newDates = [];

        foreach ($validated['schedule_ids'] as $schedule_id) {
            try {
                $missedAppointment = DB::table('schedule')
                    ->join('patients', 'schedule.patient_id', '=', 'patients.patientID')
                    ->join('users', 'patients.userID', '=', 'users.userID')
                    ->where('schedule.schedule_id', $schedule_id)
                    ->select(
                        'schedule.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email'
                    )
                    ->first();

                if (!$missedAppointment) {
                    $errors[] = "Appointment $schedule_id not found";
                    continue;
                }

                // Parse the original appointment date
                try {
                    $originalDate = $this->parseAppointmentDate($missedAppointment->appointment_date);
                } catch (\Exception $e) {
                    $errors[] = "Invalid date format for appointment $schedule_id: " . $missedAppointment->appointment_date;
                    continue;
                }

                // Calculate new appointment date (28 days from today or original date)
                $newDate = Carbon::now()->addDays(28);
                
                // Calculate next appointment date (28 days after new date)
                $nextAppointmentDate = $newDate->copy()->addDays(28);

                // Check daily limit
                $dailyCount = DB::table('schedule')
                    ->where(function($query) use ($newDate) {
                        $query->whereDate('appointment_date', $newDate->format('Y-m-d'))
                            ->orWhere(DB::raw("STR_TO_DATE(appointment_date, '%Y-%m-%d')"), $newDate->format('Y-m-d'));
                    })
                    ->where('confirmation_status', 'confirmed')
                    ->count();

                $dailyLimit = 80;
                if ($dailyCount >= $dailyLimit) {
                    $newDate->addDay();
                    $nextAppointmentDate = $newDate->copy()->addDays(28);
                }

                // Update the schedule - CHANGED: confirmation_status to 'pending'
                DB::table('schedule')
                    ->where('schedule_id', $schedule_id)
                    ->update([
                        'appointment_date' => $newDate->format('Y-m-d'),
                        'new_appointment_date' => $nextAppointmentDate->format('Y-m-d'),
                        'missed_count' => DB::raw('COALESCE(missed_count, 0) + 1'),
                        'checkup_status' => 'Pending',
                        'confirmation_status' => 'pending', // CHANGED FROM 'confirmed' TO 'pending'
                        'checkup_remarks' => 'Manually rescheduled from missed appointment on ' . Carbon::now()->format('Y-m-d'),
                        'updated_at' => now()
                    ]);

                // Send email notification to patient
                try {
                    Mail::to($missedAppointment->email)->send(
                        new AppointmentRescheduled(
                            $missedAppointment->first_name,
                            $missedAppointment->last_name,
                            $newDate->format('Y-m-d'),
                            $originalDate->format('Y-m-d')
                        )
                    );
                } catch (\Exception $emailError) {
                    \Log::error('Failed to send reschedule email: ' . $emailError->getMessage());
                    $errors[] = "Appointment rescheduled but email failed for patient: " . 
                                $missedAppointment->first_name . " " . $missedAppointment->last_name;
                }

                $successCount++;
                $newDates[] = $newDate->format('Y-m-d');
                
            } catch (\Exception $e) {
                $errors[] = "Failed to reschedule appointment $schedule_id: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Successfully rescheduled $successCount appointments",
            'new_dates' => array_unique($newDates),
            'errors' => $errors
        ]);
    }

    public function markAsCompleted(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|integer',
        ]);

        DB::table('schedule')
            ->where('patient_id', $validated['patient_id'])
            ->whereDate('appointment_date', Carbon::today())
            ->update(['checkup_status' => 'Completed']);

        return response()->json(['success' => true]);
    }

    public function rescheduleMissedAppointment(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|integer',
        ]);

        // Get the missed appointment
        $missedAppointment = DB::table('schedule')
            ->join('patients', 'schedule.patient_id', '=', 'patients.patientID')
            ->join('users', 'patients.userID', '=', 'users.userID')
            ->where('schedule.schedule_id', $validated['schedule_id'])
            ->select(
                'schedule.*',
                'users.first_name',
                'users.last_name',
                'users.email'
            )
            ->first();

        if (!$missedAppointment) {
            return response()->json(['error' => 'Appointment not found'], 404);
        }

        // Parse the original appointment date
        try {
            $originalDate = $this->parseAppointmentDate($missedAppointment->appointment_date);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid date format: ' . $missedAppointment->appointment_date], 400);
        }

        // Calculate new appointment date (28 days from today)
        $newDate = Carbon::now()->addDays(28);
        
        // Calculate next appointment date (28 days after new date)
        $nextAppointmentDate = $newDate->copy()->addDays(28);

        // Check if the new date has reached the daily limit
        $dailyCount = DB::table('schedule')
            ->where(function($query) use ($newDate) {
                $query->whereDate('appointment_date', $newDate->format('Y-m-d'))
                      ->orWhere(DB::raw("STR_TO_DATE(appointment_date, '%Y-%m-%d')"), $newDate->format('Y-m-d'));
            })
            ->where('confirmation_status', 'confirmed')
            ->count();

        $dailyLimit = 80;
        if ($dailyCount >= $dailyLimit) {
            // If limit reached, try the next day
            $newDate->addDay();
            $nextAppointmentDate = $newDate->copy()->addDays(28);
        }

        // Update the appointment
        DB::table('schedule')
            ->where('schedule_id', $validated['schedule_id'])
            ->update([
                'appointment_date' => $newDate->format('Y-m-d'),
                'new_appointment_date' => $nextAppointmentDate->format('Y-m-d'),
                'missed_count' => DB::raw('COALESCE(missed_count, 0) + 1'),
                'checkup_status' => 'Pending',
                'confirmation_status' => 'pending',
                'checkup_remarks' => 'Manually rescheduled from missed appointment on ' . Carbon::now()->format('Y-m-d'),
                'updated_at' => now()
            ]);

        // Send email notification to patient
        try {
            Mail::to($missedAppointment->email)->send(
                new AppointmentRescheduled(
                    $missedAppointment->first_name,
                    $missedAppointment->last_name,
                    $newDate->format('Y-m-d'),
                    $originalDate->format('Y-m-d')
                )
            );
        } catch (\Exception $emailError) {
            \Log::error('Failed to send reschedule email: ' . $emailError->getMessage());
            return response()->json([
                'success' => true,
                'message' => 'Appointment rescheduled but email notification failed'
            ]);
        }

        return response()->json(['success' => true]);
    }

    /**
 * Helper method to parse appointment date from various formats
 */
private function parseAppointmentDate($dateString)
{
    try {
        // If it's already a Carbon instance or DateTime
        if ($dateString instanceof \Carbon\Carbon) {
            return $dateString;
        }
        
        // If it's null or empty, return current date (shouldn't happen but for safety)
        if (empty($dateString)) {
            return Carbon::now();
        }
        
        // Try to parse as date first
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString)) {
            return Carbon::createFromFormat('Y-m-d', $dateString);
        }
        
        // Try to extract date from string (handle "2025-09-27 00:00:00" format)
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $dateString, $matches)) {
            return Carbon::createFromFormat('Y-m-d', $matches[1]);
        }
        
        // Last resort - try to parse the whole string
        return Carbon::parse($dateString);
        
    } catch (\Exception $e) {
        \Log::error("Unable to parse date: " . $dateString . " - Error: " . $e->getMessage());
        throw new \Exception("Unable to parse date: " . $dateString);
    }
}

public function getMissedAppointments()
{
    try {
        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $today = Carbon::today()->format('Y-m-d');
        
        \Log::info("=== START MISSED APPOINTMENTS QUERY ===");
        \Log::info("Today's date: " . $today);
        \Log::info("User: " . $user->userID . " - " . $user->userLevel);

        // Let's first check what appointments exist in the database
        $allAppointments = DB::table('schedule')
            ->join('patients', 'schedule.patient_id', '=', 'patients.patientID')
            ->join('users', 'patients.userID', '=', 'users.userID')
            ->select(
                'schedule.schedule_id',
                'schedule.appointment_date',
                'schedule.checkup_status',
                'schedule.confirmation_status',
                'schedule.checkup_remarks',
                'users.first_name',
                'users.last_name'
            )
            ->get();

        \Log::info("Total appointments in database: " . $allAppointments->count());
        
        foreach ($allAppointments as $appt) {
            \Log::info("Appointment - ID: " . $appt->schedule_id . 
                      ", Date: " . $appt->appointment_date . 
                      ", Checkup Status: " . $appt->checkup_status . 
                      ", Confirmation Status: " . $appt->confirmation_status .
                      ", Remarks: " . $appt->checkup_remarks);
        }

        // Now let's run the actual query with simpler conditions first
        $missedAppointments = DB::table('schedule')
            ->join('patients', 'schedule.patient_id', '=', 'patients.patientID')
            ->join('users', 'patients.userID', '=', 'users.userID')
            ->select(
                'schedule.schedule_id',
                'users.first_name',
                'users.last_name',
                'users.date_of_birth',
                'patients.hospitalNumber',
                'schedule.appointment_date',
                'schedule.checkup_status',
                'schedule.missed_count',
                'schedule.new_appointment_date',
                'schedule.reschedule_reason',
                'schedule.confirmation_status'
            )
            ->get();

        \Log::info("All appointments before filtering: " . $missedAppointments->count());

        // Manual filtering in PHP to debug
        $filteredAppointments = $missedAppointments->filter(function ($appointment) use ($today) {
            try {
                $apptDate = $this->parseAppointmentDate($appointment->appointment_date);
                $isPast = $apptDate->format('Y-m-d') < $today;
                $isPending = in_array(strtolower($appointment->checkup_status), ['pending', 'pending']);
                $hasValidRemarks = empty($appointment->checkup_remarks) || 
                                 $appointment->checkup_remarks === 'pending' ||
                                 str_contains($appointment->checkup_remarks, 'Manually rescheduled from missed appointment');
                $isNotCancelled = $appointment->confirmation_status !== 'cancelled';

                \Log::info("Appointment " . $appointment->schedule_id . 
                          " - Date: " . $appointment->appointment_date . 
                          " - Past: " . ($isPast ? 'YES' : 'NO') .
                          " - Pending: " . ($isPending ? 'YES' : 'NO') .
                          " - Valid Remarks: " . ($hasValidRemarks ? 'YES' : 'NO') .
                          " - Not Cancelled: " . ($isNotCancelled ? 'YES' : 'NO'));

                return $isPast && $isPending && $hasValidRemarks && $isNotCancelled;
            } catch (\Exception $e) {
                \Log::error("Error filtering appointment " . $appointment->schedule_id . ": " . $e->getMessage());
                return false;
            }
        })->values();

        \Log::info("Final filtered appointments: " . $filteredAppointments->count());
        \Log::info("=== END MISSED APPOINTMENTS QUERY ===");

        return response()->json([
            'appointments' => $filteredAppointments
        ]);

    } catch (\Exception $e) {
        \Log::error('Error fetching missed appointments: ' . $e->getMessage());
        return response()->json([
            'error' => 'Failed to fetch missed appointments',
            'message' => $e->getMessage()
        ], 500);
    }
}

    public function approveReschedule(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|integer',
            'approve' => 'required|boolean'
        ]);

        $schedule = DB::table('schedule')
            ->join('patients', 'schedule.patient_id', '=', 'patients.patientID')
            ->join('users', 'patients.userID', '=', 'users.userID')
            ->where('schedule.schedule_id', $validated['schedule_id'])
            ->select(
                'schedule.*',
                'users.first_name',
                'users.last_name',
                'users.email'
            )
            ->first();

        if (!$schedule) {
            return response()->json(['error' => 'Schedule not found'], 404);
        }

        if ($validated['approve']) {
            // Check if the new date has reached the daily limit
            $newDate = Carbon::parse($schedule->reschedule_requested_date)->format('Y-m-d');
            $dailyCount = DB::table('schedule')
                ->whereDate('appointment_date', $newDate)
                ->where('confirmation_status', 'confirmed')
                ->count();

            $dailyLimit = 80;
            if ($dailyCount >= $dailyLimit) {
                return response()->json([
                    'error' => 'The daily patient limit has been reached for the selected date'
                ], 400);
            }

            DB::table('schedule')
                ->where('schedule_id', $validated['schedule_id'])
                ->update([
                    'appointment_date' => $newDate,
                    'confirmation_status' => 'confirmed',
                    'reschedule_request_date' => null,
                    'reschedule_requested_date' => null,
                    'reschedule_reason' => null
                ]);

            // Send email notification to patient
            try {
                Mail::to($schedule->email)->send(
                    new AppointmentRescheduled(
                        $schedule->first_name,
                        $schedule->last_name,
                        $newDate,
                        Carbon::parse($schedule->appointment_date)->format('Y-m-d')
                    )
                );
            } catch (\Exception $emailError) {
                // Log email error but don't fail the approval
                \Log::error('Failed to send approval email: ' . $emailError->getMessage());
            }
        } else {
            DB::table('schedule')
                ->where('schedule_id', $validated['schedule_id'])
                ->update([
                    'confirmation_status' => 'pending',
                    'reschedule_request_date' => null,
                    'reschedule_requested_date' => null,
                    'reschedule_reason' => null
                ]);
        }

        return response()->json(['success' => true]);
    }

    public function getRescheduleRequests()
    {
        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $requests = DB::table('schedule')
            ->join('patients', 'schedule.patient_id', '=', 'patients.patientID')
            ->join('users', 'patients.userID', '=', 'users.userID')
            ->select(
                'schedule.schedule_id',
                'schedule.appointment_date',
                'schedule.new_appointment_date',
                'schedule.reschedule_requested_date',
                'schedule.reschedule_reason',
                'schedule.reschedule_request_date',
                'schedule.confirmation_status',
                'users.first_name',
                'users.last_name',
                'patients.hospitalNumber'
            )
            ->whereNotNull('reschedule_requested_date')
            ->orWhere(function($query) {
                $query->where('confirmation_status', 'pending_reschedule')
                      ->whereNotNull('reschedule_request_date');
            })
            ->orderBy('reschedule_request_date', 'desc')
            ->get();

        return response()->json($requests);
    }

    public function archiveRescheduleReason(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|integer',
            'is_past' => 'sometimes|boolean'
        ]);

        $user = auth()->user();
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            // Get the schedule data
            $schedule = DB::table('schedule')
                ->where('schedule_id', $validated['schedule_id'])
                ->first();

            if (!$schedule) {
                return response()->json(['error' => 'Schedule not found'], 404);
            }

            // Archive the reschedule data
            DB::table('archive')->insert([
                'archived_data' => json_encode([
                    'original_date' => $schedule->appointment_date,
                    'requested_date' => $schedule->reschedule_requested_date,
                    'reason' => $schedule->reschedule_reason,
                    'patient_id' => $schedule->patient_id,
                    'is_past_reschedule' => $validated['is_past'] ?? false
                ]),
                'archived_from_table' => 'schedule',
                'archived_by' => $user->userID,
                'archived_date' => now(),
            ]);

            // Clear the reschedule fields if it's a current reschedule
            if (!($validated['is_past'] ?? false)) {
                DB::table('schedule')
                    ->where('schedule_id', $validated['schedule_id'])
                    ->update([
                        'reschedule_request_date' => null,
                        'reschedule_requested_date' => null,
                        'reschedule_reason' => null,
                        'confirmation_status' => 'pending',
                    ]);
            } else {
                // For past reschedules, just clear the reason
                DB::table('schedule')
                    ->where('schedule_id', $validated['schedule_id'])
                    ->update([
                        'reschedule_reason' => null,
                    ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Reschedule reason archived successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to archive reschedule reason: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request)
    {
        try {
            $request->validate([
                'status' => 'required|string|in:in Duty,on duty,off duty',
            ]);

            $user = auth()->user();
            
            if ($user->userLevel === 'patient') {
                return response()->json([
                    'success' => false,
                    'message' => 'Patients cannot update their status'
                ], 403);
            }

            // Normalize status to 'in Duty' or 'off duty'
            $normalizedStatus = strtolower($request->status) === 'on duty' ? 'in Duty' : $request->status;
            
            $user->update([
                'TodaysStatus' => $normalizedStatus
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'user' => $user->fresh()
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to update status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

     public function getCompletedCheckups()
{
    $user = auth()->user();
    
    if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
        return response()->json(['error' => 'Unauthorized'], 403);
    }

    // Get completed checkups with proper joins
    $completedCheckups = DB::table('schedule')
        ->join('patients', 'schedule.patient_id', '=', 'patients.patientID')
        ->join('users', 'patients.userID', '=', 'users.userID')
        ->where('schedule.checkup_status', 'Completed')
        ->select(
            'schedule.schedule_id',
            'patients.patientID',
            'schedule.appointment_date',
            'schedule.checkup_remarks',
            'users.first_name',
            'users.last_name',
            'users.userID',
            'patients.hospitalNumber',
            'schedule.patient_id as schedule_patient_id'
        )
        ->orderBy('schedule.appointment_date', 'desc')
        ->get();

    // Get all prescriptions
    $prescriptions = DB::table('prescriptions')->get();
    
    // Create prescription map
    $prescriptionMap = [];
    foreach ($prescriptions as $prescription) {
        $prescriptionMap[$prescription->patientID] = $prescription;
    }

    return response()->json([
        'completedCheckups' => $completedCheckups,
        'prescriptions' => $prescriptionMap,
        'counts' => [
            'total_completed' => $completedCheckups->count(),
            'total_with_prescriptions' => count($prescriptionMap),
            'todays_completed' => $completedCheckups->filter(function($checkup) {
                return Carbon::parse($checkup->appointment_date)->isToday();
            })->count()
        ]
    ]);
}

   public function completeCheckup(Request $request)
    {
        try {
            $validated = $request->validate([
                'patient_id' => 'required|integer', // This should be the patientID from patients table
                'schedule_id' => 'required|integer'
            ]);

            $user = auth()->user();
            
            if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Get current appointment details - FIXED: Use correct patientID
            $schedule = DB::table('schedule')
                ->join('patients', 'schedule.patient_id', '=', 'patients.patientID') // CORRECT JOIN
                ->join('users', 'patients.userID', '=', 'users.userID')
                ->where('schedule.schedule_id', $validated['schedule_id'])
                ->where('patients.patientID', $validated['patient_id']) // CORRECT: Use patientID from patients table
                ->select(
                    'schedule.*',
                    'patients.patientID', // CORRECT patientID
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.userID'
                )
                ->first();

            if (!$schedule) {
                return response()->json(['error' => 'Schedule not found'], 404);
            }

            // Parse the current appointment date
            try {
                $currentAppointmentDate = $this->parseAppointmentDate($schedule->appointment_date);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Invalid current appointment date format: ' . $schedule->appointment_date], 400);
            }

            // Calculate new appointment date (28 days from current appointment date)
            $nextAppointmentDate = $currentAppointmentDate->copy()->addDays(28)->format('Y-m-d');
            
            // Calculate the appointment after next (28 days after next appointment)
            $nextNextAppointmentDate = Carbon::parse($nextAppointmentDate)->addDays(28)->format('Y-m-d');

            // Update the schedule according to your requirements
            DB::table('schedule')
                ->where('schedule_id', $validated['schedule_id'])
                ->update([
                    'appointment_date' => $nextAppointmentDate,
                    'new_appointment_date' => $nextNextAppointmentDate,
                    'confirmation_status' => 'pending',
                    'checkup_status' => 'Pending',
                    'checkup_remarks' => 'Pending',
                    'updated_at' => now()
                ]);

            // Get prescription details for email - FIXED: Use correct patientID
            $prescription = DB::table('prescriptions')
                ->where('patientID', $schedule->patientID) // CORRECT: Use the actual patientID
                ->first();

            // Prepare prescription details for email
            $prescriptionDetails = null;
            if ($prescription) {
                $prescriptionDetails = [
                    'pd_bag_counts' => $prescription->pd_bag_counts,
                    'pd_bag_percentages' => $prescription->pd_bag_percentages,
                    'additional_instructions' => $prescription->additional_instructions
                ];
            }

            // Send email to patient
            try {
                Mail::to($schedule->email)->send(
                    new CheckupCompleted(
                        $schedule->first_name . ' ' . $schedule->last_name,
                        $currentAppointmentDate->format('Y-m-d'),
                        $nextAppointmentDate,
                        $prescriptionDetails
                    )
                );
            } catch (\Exception $emailError) {
                // Log email error but don't fail the process
                \Log::error('Failed to send completion email: ' . $emailError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Checkup completed successfully! Next appointment scheduled for ' . 
                            Carbon::parse($nextAppointmentDate)->format('F j, Y'),
                'next_appointment_date' => $nextAppointmentDate,
                'updated_schedule' => [
                    'appointment_date' => $nextAppointmentDate,
                    'new_appointment_date' => $nextNextAppointmentDate,
                    'confirmation_status' => 'pending',
                    'checkup_status' => 'Pending',
                    'checkup_remarks' => 'Pending'
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to complete checkup: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete checkup',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    // ... (oth

    public function sendReminderEmail(Request $request)
    {
        try {
            $validated = $request->validate([
                'schedule_id' => 'required|integer',
                'patient_name' => 'required|string',
                'appointment_date' => 'required|string'
            ]);

            $user = auth()->user();
            
            if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Get patient email and details
            $schedule = DB::table('schedule')
                ->join('patients', 'schedule.patient_id', '=', 'patients.patientID')
                ->join('users', 'patients.userID', '=', 'users.userID')
                ->where('schedule.schedule_id', $validated['schedule_id'])
                ->select('users.email', 'users.first_name', 'users.last_name', 'schedule.appointment_date')
                ->first();

            if (!$schedule) {
                return response()->json(['error' => 'Schedule not found'], 404);
            }

            // Check if appointment is today
            $isToday = Carbon::parse($schedule->appointment_date)->isToday();

            // Send reminder email
            Mail::to($schedule->email)->send(new \App\Mail\AppointmentReminder(
                $validated['patient_name'],
                $validated['appointment_date'],
                $isToday
            ));

            return response()->json(['success' => true, 'message' => 'Reminder email sent successfully']);

        } catch (\Exception $e) {
            \Log::error('Failed to send reminder email: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to send reminder email',
                'message' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function manualRescheduleMissed(Request $request)
{
    $validated = $request->validate([
        'schedule_ids' => 'required|array',
        'schedule_ids.*' => 'integer',
        'new_date' => 'required|date' // Removed 'after:today' validation
    ]);

    $successCount = 0;
    $errors = [];

    foreach ($validated['schedule_ids'] as $schedule_id) {
        try {
            $missedAppointment = DB::table('schedule')
                ->join('patients', 'schedule.patient_id', '=', 'patients.patientID')
                ->join('users', 'patients.userID', '=', 'users.userID')
                ->where('schedule.schedule_id', $schedule_id)
                ->select(
                    'schedule.*',
                    'users.first_name',
                    'users.last_name',
                    'users.email'
                )
                ->first();

            if (!$missedAppointment) {
                $errors[] = "Appointment $schedule_id not found";
                continue;
            }

            // Parse the original appointment date
            try {
                $originalDate = $this->parseAppointmentDate($missedAppointment->appointment_date);
            } catch (\Exception $e) {
                $errors[] = "Invalid date format for appointment $schedule_id: " . $missedAppointment->appointment_date;
                continue;
            }

            // Parse the new date
            $newDate = Carbon::parse($validated['new_date']);
            
            // Calculate next appointment date (28 days after new date)
            $nextAppointmentDate = $newDate->copy()->addDays(28);

            // Check daily limit for the new date
            $dailyCount = DB::table('schedule')
                ->where(function($query) use ($newDate) {
                    $query->whereDate('appointment_date', $newDate->format('Y-m-d'))
                        ->orWhere(DB::raw("STR_TO_DATE(appointment_date, '%Y-%m-%d')"), $newDate->format('Y-m-d'));
                })
                ->where('confirmation_status', 'confirmed')
                ->count();

            $dailyLimit = 80;
            if ($dailyCount >= $dailyLimit) {
                $errors[] = "Daily limit reached for date " . $newDate->format('Y-m-d') . " - cannot reschedule appointment $schedule_id";
                continue;
            }

            // Update the schedule
            DB::table('schedule')
                ->where('schedule_id', $schedule_id)
                ->update([
                    'appointment_date' => $newDate->format('Y-m-d'),
                    'new_appointment_date' => $nextAppointmentDate->format('Y-m-d'),
                    'reschedule_requested_date' => $newDate->format('Y-m-d'), // Store the requested date
                    'missed_count' => DB::raw('COALESCE(missed_count, 0) + 1'),
                    'checkup_status' => 'Pending',
                    'confirmation_status' => 'pending',
                    'checkup_remarks' => 'Manually rescheduled from missed appointment on ' . Carbon::now()->format('Y-m-d'),
                    'updated_at' => now()
                ]);

            // Send email notification to patient
            try {
                Mail::to($missedAppointment->email)->send(
                    new AppointmentRescheduled(
                        $missedAppointment->first_name,
                        $missedAppointment->last_name,
                        $newDate->format('Y-m-d'),
                        $originalDate->format('Y-m-d')
                    )
                );
            } catch (\Exception $emailError) {
                \Log::error('Failed to send reschedule email: ' . $emailError->getMessage());
                $errors[] = "Appointment rescheduled but email failed for patient: " . 
                            $missedAppointment->first_name . " " . $missedAppointment->last_name;
            }

            $successCount++;
            
        } catch (\Exception $e) {
            $errors[] = "Failed to reschedule appointment $schedule_id: " . $e->getMessage();
        }
    }

    return response()->json([
        'success' => true,
        'message' => "Successfully rescheduled $successCount appointments to " . Carbon::parse($validated['new_date'])->format('F j, Y'),
        'errors' => $errors
    ]);
}
}