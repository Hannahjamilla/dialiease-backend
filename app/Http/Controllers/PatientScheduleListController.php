<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PatientScheduleListController extends Controller
{
    /**
     * Display a listing of all patient schedules.
     */
    public function index(Request $request)
    {
        try {
            $schedules = Schedule::with(['patient', 'user'])
                ->orderBy('appointment_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Patient schedules retrieved successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve patient schedules.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of upcoming patient schedules.
     */
    public function upcoming(Request $request)
    {
        try {
            $today = Carbon::today()->format('Y-m-d');
            
            $schedules = Schedule::with(['patient', 'user'])
                ->whereDate('appointment_date', '>=', $today)
                ->whereIn('checkup_status', ['pending', 'Pending'])
                ->where(function($query) {
                    $query->whereNull('checkup_remarks')
                          ->orWhere('checkup_remarks', 'pending');
                })
                ->orderBy('appointment_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Upcoming patient schedules retrieved successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve upcoming patient schedules.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of missed patient schedules.
     */
    public function missed(Request $request)
    {
        try {
            $today = Carbon::today()->format('Y-m-d');
            
            $schedules = Schedule::with(['patient', 'user'])
                ->whereDate('appointment_date', '<', $today)
                ->whereIn('checkup_status', ['pending', 'Pending'])
                ->where(function($query) {
                    $query->whereNull('checkup_remarks')
                          ->orWhere('checkup_remarks', 'pending');
                })
                ->orderBy('appointment_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Missed patient schedules retrieved successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve missed patient schedules.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display a listing of completed patient schedules.
     */
    public function completed(Request $request)
    {
        try {
            $schedules = Schedule::with(['patient', 'user'])
                ->where('checkup_status', 'completed')
                ->orderBy('appointment_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $schedules,
                'message' => 'Completed patient schedules retrieved successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve completed patient schedules.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get reschedule requests
     */
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
}