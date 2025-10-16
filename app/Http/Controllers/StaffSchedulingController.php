<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Patient;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StaffSchedulingController extends Controller
{
    /**
     * Get all patients without scheduled appointments
     *
     * @return \Illuminate\Http\Response
     */
    public function getUnscheduledPatients()
    {
        try {
            $patients = Patient::select([
                    'patients.patientID as id',
                    'patients.hospitalNumber',
                    'patients.userID',
                    'users.first_name',
                    'users.middle_name',
                    'users.last_name',
                    'users.email',
                    'users.phone_number',
                    DB::raw('(SELECT MAX(appointment_date) FROM schedule WHERE schedule.patient_id = patients.patientID) as last_visit_date')
                ])
                ->leftJoin('users', 'patients.userID', '=', 'users.userID')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('schedule')
                        ->whereColumn('schedule.patient_id', 'patients.patientID')
                        ->where('schedule.appointment_date', '>=', now());
                })
                ->orderBy('patients.created_at', 'desc')
                ->get();

            return response()->json($patients);
        } catch (\Exception $e) {
            Log::error('Failed to fetch unscheduled patients: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to retrieve unscheduled patients',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Schedule a new appointment
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function scheduleAppointment(Request $request)
    {
        try {
            $validated = $request->validate([
                'patient_id' => 'required|exists:patients,patientID',
                'userID' => 'required|exists:users,userID',
                'appointment_date' => 'required|date|after:now',
                'remarks' => 'nullable|string|max:500'
            ]);

            $appointment = Schedule::create([
                'patient_id' => $validated['patient_id'],
                'userID' => $validated['userID'],
                'appointment_date' => $validated['appointment_date'],
                'confirmation_status' => 'pending',
                'checkup_status' => 'scheduled',
                'remarks' => $validated['remarks'] ?? null,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'message' => 'Appointment scheduled successfully',
                'data' => $appointment
            ], 201);

        } catch (\Exception $e) {
            Log::error('Appointment scheduling failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to schedule appointment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}