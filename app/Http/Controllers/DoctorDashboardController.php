<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;
use App\Models\Patient;
use App\Models\User;
use App\Models\Prescription;
use App\Models\Queue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DoctorDashboardController extends Controller
{
    public function getDashboardData()
    {
        try {
            $doctorId = Auth::id();
            
            // Get today's date
            $today = Carbon::today()->toDateString();
            
            // Get assigned patients from queue for today with proper joins
            $assignedPatients = DB::table('queue')
                ->join('users', 'queue.userID', '=', 'users.userID')
                ->leftJoin('patients', 'queue.userID', '=', 'patients.userID')
                ->whereDate('queue.appointment_date', $today)
                ->where('queue.doctor_id', $doctorId)
                ->where('queue.status', 'in-progress')
                ->orderBy('queue.queue_number', 'asc')
                ->select(
                    'queue.queue_id',
                    'queue.userID as patient_id',
                    'queue.queue_number',
                    'queue.appointment_date',
                    'queue.status',
                    'queue.emergency_status',
                    'queue.emergency_priority',
                    'queue.start_time',
                    'queue.doctor_id',
                    'users.first_name',
                    'users.last_name',
                    'users.gender',
                    'users.date_of_birth',
                    'users.phone_number',
                    'users.email',
                    // 'users.address',
                    'patients.hospitalNumber',
                    'patients.legalRepresentative'
                )
                ->get()
                ->map(function ($patient) {
                    return [
                        'queue_id' => $patient->queue_id,
                        'patient_id' => $patient->patient_id,
                        'patientID' => $patient->patient_id,
                        'first_name' => $patient->first_name,
                        'last_name' => $patient->last_name,
                        'hospitalNumber' => $patient->hospitalNumber ?? 'N/A',
                        'appointment_date' => $patient->appointment_date,
                        'queue_number' => $patient->queue_number,
                        'status' => $patient->status,
                        'emergency_status' => (bool)$patient->emergency_status,
                        'emergency_priority' => $patient->emergency_priority,
                        'start_time' => $patient->start_time,
                        'doctor_id' => $patient->doctor_id,
                        'gender' => $patient->gender,
                        'date_of_birth' => $patient->date_of_birth,
                        'phone_number' => $patient->phone_number,
                        'email' => $patient->email,
                        // 'address' => $patient->address,
                        'legalRepresentative' => $patient->legalRepresentative
                    ];
                });

            // Get all schedules for patients with patient and user data
            $allSchedules = Schedule::with(['patient.user', 'patient', 'patient.schedules' => function($query) {
                $query->where('appointment_date', '>', Carbon::now())
                      ->orderBy('appointment_date', 'asc')
                      ->limit(1);
            }])
            ->whereHas('patient.user', function($query) {
                $query->where('userLevel', 'patient');
            })
            ->orderBy('appointment_date', 'asc')
            ->get()
            ->map(function ($schedule) {
                $nextAppointment = $schedule->patient->schedules->first();
                
                return [
                    'schedule_id' => $schedule->schedule_id,
                    'patient_id' => $schedule->patient_id,
                    'patientID' => $schedule->patient_id,
                    'first_name' => $schedule->patient->user->first_name,
                    'last_name' => $schedule->patient->user->last_name,
                    'hospitalNumber' => $schedule->patient->hospitalNumber,
                    'appointment_date' => $schedule->appointment_date,
                    'confirmation_status' => $schedule->confirmation_status,
                    'checkup_status' => $schedule->checkup_status,
                    'remarks' => $schedule->remarks,
                    'reschedule_requested_date' => $schedule->reschedule_requested_date,
                    'reschedule_reason' => $schedule->reschedule_reason,
                    'doctor_id' => $schedule->userID,
                    // Add patient demographic information
                    'gender' => $schedule->patient->user->gender,
                    'date_of_birth' => $schedule->patient->user->date_of_birth,
                    'phone_number' => $schedule->patient->user->phone_number,
                    'email' => $schedule->patient->user->email,
                    // 'address' => $schedule->patient->user->address,

                    // Add next appointment if exists
                    'next_appointment' => $nextAppointment ? $nextAppointment->appointment_date : null,
                ];
            });

            // Filter today's patients - only show those with checkup_status not 'Completed'
            $patientsToday = $allSchedules->filter(function ($schedule) use ($today) {
                return Carbon::parse($schedule['appointment_date'])->isToday() && 
                       $schedule['checkup_status'] !== 'Completed';
            })->values();

            // Get upcoming appointments (next 7 days) - only show those with checkup_status not 'Completed'
            $upcomingAppointments = $allSchedules->filter(function ($schedule) {
                return Carbon::parse($schedule['appointment_date'])->isFuture() && 
                       $schedule['checkup_status'] !== 'Completed';
            })->take(7)->values();

            // Get confirmed patients - only show those with checkup_status not 'Completed'
            $confirmedPatients = $allSchedules->filter(function ($schedule) {
                return $schedule['confirmation_status'] === 'confirmed' && 
                       $schedule['checkup_status'] !== 'Completed';
            })->values();

            // Get rescheduled patients
            $rescheduledPatients = $allSchedules->filter(function ($schedule) {
                return $schedule['confirmation_status'] === 'pending_reschedule';
            })->values();

            // Counts for stats
            $counts = [
                'pending' => $allSchedules->where('checkup_status', 'Pending')->count(),
                'in_progress' => $allSchedules->where('checkup_status', 'In Progress')->count(),
                'completed' => $allSchedules->where('checkup_status', 'Completed')->count(),
                'assigned' => $assignedPatients->count(),
            ];

            return response()->json([
                'success' => true,
                'assignedPatients' => $assignedPatients,
                'allSchedules' => $allSchedules,
                'patientsToday' => $patientsToday,
                'appointments' => $upcomingAppointments,
                'confirmedPatients' => $confirmedPatients,
                'rescheduledPatients' => $rescheduledPatients,
                'counts' => $counts,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function markAsCompleted(Request $request)
    {
        try {
            $request->validate([
                'patient_id' => 'required|integer|exists:schedule,patient_id',
            ]);

            // Find the schedule for this patient
            $schedule = Schedule::where('patient_id', $request->patient_id)
                ->whereDate('appointment_date', Carbon::today())
                ->firstOrFail();

            // Update the checkup status
            $schedule->checkup_status = 'Completed';
            $schedule->save();

            return response()->json([
                'success' => true,
                'message' => 'Patient marked as completed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark as completed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function approveReschedule(Request $request)
    {
        try {
            $request->validate([
                'schedule_id' => 'required|integer|exists:schedule,schedule_id',
                'approve' => 'required|boolean',
            ]);

            // Find the schedule
            $schedule = Schedule::findOrFail($request->schedule_id);

            if ($request->approve) {
                // Approve the reschedule
                $schedule->appointment_date = $schedule->reschedule_requested_date;
                $schedule->confirmation_status = 'confirmed';
                $schedule->reschedule_requested_date = null;
                $schedule->reschedule_reason = null;
                $schedule->save();
            } else {
                // Reject the reschedule
                $schedule->confirmation_status = 'confirmed';
                $schedule->reschedule_requested_date = null;
                $schedule->reschedule_reason = null;
                $schedule->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Reschedule request ' . ($request->approve ? 'approved' : 'rejected') . ' successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process reschedule request: ' . $e->getMessage()
            ], 500);
        }
    }
}