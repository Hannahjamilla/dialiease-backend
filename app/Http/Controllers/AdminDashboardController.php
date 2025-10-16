<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use App\Models\User;
use App\Models\Patient;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    // Get dashboard statistics
    public function getDashboardStats()
    {
        try {
            $doctorCount = User::where('userLevel', 'doctor')->count();
            $nurseCount = User::where('userLevel', 'nurse')->count();
            $patientCount = Patient::count();
            
            $appointmentCount = Schedule::where('confirmation_status', 'confirmed')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'doctorCount' => $doctorCount,
                    'nurseCount' => $nurseCount,
                    'patientCount' => $patientCount,
                    'appointmentCount' => $appointmentCount,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Dashboard stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get appointment counts for today, tomorrow, and next 3 days
    public function getAppointmentCounts()
    {
        try {
            $today = Carbon::today()->format('Y-m-d');
            $tomorrow = Carbon::tomorrow()->format('Y-m-d');
            $nextThreeDaysStart = Carbon::today()->addDays(2)->format('Y-m-d');
            $nextThreeDaysEnd = Carbon::today()->addDays(4)->format('Y-m-d');

            $todayCount = Schedule::whereDate('appointment_date', $today)
                ->where('confirmation_status', 'confirmed')
                ->count();

            $tomorrowCount = Schedule::whereDate('appointment_date', $tomorrow)
                ->where('confirmation_status', 'confirmed')
                ->count();

            $nextThreeDaysCount = Schedule::whereBetween('appointment_date', [$nextThreeDaysStart, $nextThreeDaysEnd])
                ->where('confirmation_status', 'confirmed')
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'today' => $todayCount,
                    'tomorrow' => $tomorrowCount,
                    'nextThreeDays' => $nextThreeDaysCount
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Appointment counts error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch appointment counts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Reminders CRUD operations
    public function getReminders()
    {
        try {
            $reminders = Reminder::orderBy('created_at', 'desc')
                ->get()
                ->map(function($reminder) {
                    return [
                        'id' => $reminder->id,
                        'text' => $reminder->text,
                        'date' => $reminder->date ? $reminder->date->format('Y-m-d') : null,
                        'completed' => (bool)$reminder->completed,
                        'created_at' => $reminder->created_at->format('Y-m-d H:i:s')
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $reminders
            ]);

        } catch (\Exception $e) {
            \Log::error('Get reminders error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch reminders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function addReminder(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'text' => 'required|string|max:255',
                'date' => 'required|date',
                'completed' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $reminder = Reminder::create([
                'text' => $request->text,
                'date' => $request->date,
                'completed' => $request->completed ?? false,
                'user_id' => auth()->id() // Add user association if needed
            ]);

            return response()->json([
                'success' => true,
                'data' => $reminder,
                'message' => 'Reminder added successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Add reminder error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add reminder',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateReminder(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'completed' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $reminder = Reminder::findOrFail($id);
            $reminder->update([
                'completed' => $request->completed
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reminder updated successfully',
                'data' => $reminder
            ]);

        } catch (\Exception $e) {
            \Log::error('Update reminder error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update reminder',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteReminder($id)
    {
        try {
            $reminder = Reminder::findOrFail($id);
            $reminder->delete();

            return response()->json([
                'success' => true,
                'message' => 'Reminder deleted successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Delete reminder error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete reminder',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get age distribution data by month
public function getAgeDistribution()
    {
        // Get all patient DOBs
        $patients = DB::table('users')
            ->select('date_of_birth')
            ->where('userLevel', 'patient')
            ->get();

        // Prepare data arrays for Jan–Dec
        $minors = array_fill(0, 12, 0);
        $adults = array_fill(0, 12, 0);
        $seniors = array_fill(0, 12, 0);

        foreach ($patients as $patient) {
            if (!$patient->date_of_birth) {
                continue;
            }

            $dob = Carbon::parse($patient->date_of_birth);
            $age = $dob->age; // automatically calculates age today
            $monthIndex = (int) $dob->format('n') - 1; // 0-based month index

            if ($age <= 17) {
                $minors[$monthIndex]++;
            } elseif ($age <= 59) {
                $adults[$monthIndex]++;
            } else {
                $seniors[$monthIndex]++;
            }
        }

        return response()->json([
            'ageDistribution' => [
                'minors' => $minors,
                'adults' => $adults,
                'seniors' => $seniors
            ]
        ]);
    }

}