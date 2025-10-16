<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Patient;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Mail\PatientCredentialsMail;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class PatientRegistrationController extends Controller
{
    public function checkEmail(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid email format'
                ], 422);
            }

            $exists = User::where('email', $request->email)->exists();

            return response()->json([
                'success' => true,
                'exists' => $exists,
                'message' => $exists ? 'Email already exists' : 'Email available'
            ]);

        } catch (\Exception $e) {
            Log::error('Email check failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error during email check'
            ], 500);
        }
    }

    public function sendOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid email format'
                ], 422);
            }

            $otp = rand(100000, 999999);
            $expiresAt = now()->addMinutes(10);

            cache()->put('otp_'.$request->email, [
                'otp' => $otp,
                'expires_at' => $expiresAt
            ], $expiresAt);

            Mail::to($request->email)->send(new OtpMail($otp));

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('OTP send failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send OTP'
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'otp' => 'required|digits:6'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'error' => $validator->errors()->first()
                ], 422);
            }

            $cachedOtp = cache()->get('otp_'.$request->email);

            if (!$cachedOtp) {
                return response()->json([
                    'success' => false,
                    'verified' => false,
                    'message' => 'OTP expired or not found'
                ], 400);
            }

            if ($cachedOtp['otp'] != $request->otp) {
                return response()->json([
                    'success' => false,
                    'verified' => false,
                    'message' => 'Invalid OTP'
                ], 400);
            }

            if (now()->gt($cachedOtp['expires_at'])) {
                return response()->json([
                    'success' => false,
                    'verified' => false,
                    'message' => 'OTP expired'
                ], 400);
            }

            cache()->forget('otp_'.$request->email);

            return response()->json([
                'success' => true,
                'verified' => true,
                'message' => 'OTP verified successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('OTP verification failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Server error during OTP verification'
            ], 500);
        }
    }

    public function sendPhoneOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|regex:/^09\d{9}$/'
        ], [
            'phone_number.regex' => 'Please enter a valid Philippine mobile number (09xxxxxxxxx)'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => $validator->errors()->first()
            ], 422);
        }

        // Generate OTP
        $otp = rand(100000, 999999);
        $expiresAt = now()->addMinutes(10);

        // Store in cache
        cache()->put('phone_otp_'.$request->phone_number, [
            'otp' => $otp,
            'expires_at' => $expiresAt
        ], $expiresAt);

        // Development mode - don't send real SMS
        if (app()->environment('local')) {
            Log::info("[DEV] OTP for {$request->phone_number}: {$otp}");
            return response()->json([
                'success' => true,
                'message' => 'OTP generated (dev mode)',
                'otp' => $otp // Only returned in development!
            ]);
        }

        // Production - send real SMS
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post('https://api.semaphore.co/api/v4/messages', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'form_params' => [
                    'apikey' => config('services.semaphore.key'),
                    'number' => $request->phone_number,
                    'message' => "Your MediCare verification code: $otp\nValid for 10 minutes.",
                    'sendername' => config('services.semaphore.sender_name')
                ]
            ]);

            $responseBody = json_decode($response->getBody(), true);

            if (isset($responseBody[0]['status'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'OTP sent successfully'
                ]);
            }

            throw new \Exception('Failed to send SMS');

        } catch (\Exception $e) {
            Log::error('Semaphore SMS Error: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to send verification code. Please try again later.'
            ], 500);
        }
    }

    public function verifyPhoneOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|regex:/^09\d{9}$/',
            'otp' => 'required|digits:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid verification data'
            ], 422);
        }

        $cachedOtp = cache()->get('phone_otp_'.$request->phone_number);

        if (!$cachedOtp) {
            return response()->json([
                'success' => false,
                'error' => 'OTP expired or not found'
            ], 400);
        }

        if ($cachedOtp['otp'] != $request->otp) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid OTP code'
            ], 400);
        }

        if (now()->gt($cachedOtp['expires_at'])) {
            return response()->json([
                'success' => false,
                'error' => 'OTP has expired'
            ], 400);
        }

        // Clear OTP from cache
        cache()->forget('phone_otp_'.$request->phone_number);

        return response()->json([
            'success' => true,
            'message' => 'Phone number verified successfully'
        ]);
    }
    

    public function getAvailableDates()
    {
        try {
            $dates = [];
            $startDate = Carbon::today();
            $endDate = Carbon::today()->addDays(30);
            $maxAppointments = 80;

            // Generate fallback dates first in case database query fails
            $fallbackDates = [];
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $fallbackDates[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'available_slots' => $maxAppointments,
                    'day_name' => $currentDate->englishDayOfWeek,
                    'day_number' => $currentDate->dayOfWeek,
                    'formatted_date' => $currentDate->format('F j, Y')
                ];
                $currentDate->addDay();
            }

            // Try to get real data from database
            try {
                $dbDates = Schedule::whereBetween('appointment_date', [$startDate, $endDate])
                    ->whereNotIn('status', ['cancelled', 'completed'])
                    ->selectRaw('DATE(appointment_date) as date, COUNT(*) as count')
                    ->groupBy('date')
                    ->get()
                    ->keyBy('date');

                    while ($startDate <= $endDate) {
                    $dateStr = $startDate->format('Y-m-d');
                    
                    // Remove the weekend check
                    $appointmentCount = $dbDates->has($dateStr) ? $dbDates[$dateStr]->count : 0;
                    $availableSlots = $maxAppointments - $appointmentCount;
                    
                    if ($availableSlots > 0) {
                        $dates[] = [
                            'date' => $dateStr,
                            'available_slots' => $availableSlots,
                            'day_name' => $startDate->englishDayOfWeek,
                            'day_number' => $startDate->dayOfWeek,
                            'formatted_date' => $startDate->format('F j, Y')
                        ];
                    }
                    $startDate->addDay();
                }
            } catch (\Exception $dbError) {
                // If database query fails, use fallback dates
                Log::error('Database query failed in getAvailableDates: ' . $dbError->getMessage());
                $dates = $fallbackDates;
            }

            // If no dates found (shouldn't happen with fallback), use tomorrow as default
            if (empty($dates)) {
                $tomorrow = Carbon::tomorrow();
                if ($tomorrow->isWeekend()) {
                    $tomorrow->next(Carbon::MONDAY);
                }
                
                $dates[] = [
                    'date' => $tomorrow->format('Y-m-d'),
                    'available_slots' => $maxAppointments,
                    'day_name' => $tomorrow->englishDayOfWeek,
                    'day_number' => $tomorrow->dayOfWeek,
                    'formatted_date' => $tomorrow->format('F j, Y')
                ];
            }

            return response()->json([
                'success' => true,
                'dates' => $dates,
                'max_appointments_per_day' => $maxAppointments
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch available dates: ' . $e->getMessage());
            
            // Return fallback dates even in case of complete failure
            $tomorrow = Carbon::tomorrow();
            if ($tomorrow->isWeekend()) {
                $tomorrow->next(Carbon::MONDAY);
            }
            
            return response()->json([
                'success' => false,
                'dates' => [[
                    'date' => $tomorrow->format('Y-m-d'),
                    'available_slots' => 80,
                    'day_name' => $tomorrow->englishDayOfWeek,
                    'day_number' => $tomorrow->dayOfWeek,
                    'formatted_date' => $tomorrow->format('F j, Y')
                ]],
                'message' => 'Using fallback dates due to server error'
            ]);
        }
    }

    private function generateSecurePassword()
    {
        // Define character sets
        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $specialChars = '!@#$%^&*()_+';
        
        // Ensure at least one character from each set
        $password = $lowercase[rand(0, strlen($lowercase) - 1)];
        $password .= $uppercase[rand(0, strlen($uppercase) - 1)];
        $password .= $numbers[rand(0, strlen($numbers) - 1)];
        $password .= $specialChars[rand(0, strlen($specialChars) - 1)];
        
        // Fill the rest with random characters
        $allChars = $lowercase . $uppercase . $numbers . $specialChars;
        for ($i = strlen($password); $i < 12; $i++) {
            $password .= $allChars[rand(0, strlen($allChars) - 1)];
        }
        
        // Shuffle the password to make it more random
        return str_shuffle($password);
    }

    public function registerPatient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255|regex:/^[A-Za-z]{2,}(?: [A-Za-z]{2,})*$/',
            'middle_name' => 'nullable|string|max:255|regex:/^[A-Za-z]{2,}(?: [A-Za-z]{2,})*$/',
            'last_name' => 'required|string|max:255|regex:/^[A-Za-z]{2,}(?: [A-Za-z]{2,})*$/',
            'suffix' => 'nullable|string|max:5|regex:/^[A-Za-z.]{1,5}$/',
            'email' => 'required|email|unique:users,email',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:Male,Female,Other',
            'phone_number' => 'nullable|string|regex:/^09\d{9}$/|size:11',
            'legalRepresentative' => 'nullable|string|max:255',
            'appointment_date' => 'required|date|after_or_equal:today'
        ], [
            'first_name.regex' => 'First name must contain only letters and be at least 2 characters',
            'middle_name.regex' => 'Middle name must contain only letters if provided',
            'last_name.regex' => 'Last name must contain only letters and be at least 2 characters',
            'suffix.regex' => 'Suffix must be 1-5 characters (letters or dot)',
            'phone_number.regex' => 'Please enter a valid Philippine mobile number (09xxxxxxxxx)',
            'appointment_date.after_or_equal' => 'Appointment date must be today or in the future'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dob = Carbon::parse($request->date_of_birth);
        if ($dob->age < 1) {
            return response()->json([
                'success' => false,
                'errors' => ['date_of_birth' => ['Patient must be at least 1 year old']]
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Generate secure password
            $password = $this->generateSecurePassword();

            // Create user
            $user = User::create([
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'suffix' => $request->suffix,
                'email' => $request->email,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'phone_number' => $request->phone_number, // Will be null if not provided
                'password' => Hash::make($password),
                'userLevel' => 'patient',
                'status' => 'inactive',
                'EmpStatus' => 'active'
            ]);
            
            // Generate unique hospital number
            do {
                $hospitalNumber = 'HN-' . str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            } while (Patient::where('hospitalNumber', $hospitalNumber)->exists());
            
            // Create patient with AccStatus set to 'pending'
            $patient = Patient::create([
                'userID' => $user->userID,
                'hospitalNumber' => $hospitalNumber,
                'legalRepresentative' => $request->legalRepresentative,
                'AccStatus' => 'pending'
            ]);

            // Calculate follow-up date (exactly 28 days later)
            $followUpDate = Carbon::parse($request->appointment_date)->addDays(28);

            // Create ONLY the initial appointment - FIXED: Removed double registration
            $appointment = Schedule::create([
                'userID' => $user->userID,
                'patient_id' => $patient->patientID,
                'appointment_date' => $request->appointment_date,
                'new_appointment_date' => $followUpDate, // Store the follow-up date for reference
                'confirmation_status' => 'confirmed',
                'checkup_status' => 'pending',
                'remarks' => 'Initial appointment'
            ]);

            // Send patient credentials email
            Mail::to($user->email)->send(new PatientCredentialsMail(
                $user->email,
                $password,
                $hospitalNumber,
                $request->appointment_date,
                $followUpDate,
                $user->first_name.' '.$user->last_name,
                $user->date_of_birth,
                $user->gender,
                $user->phone_number
            ));

            DB::commit();

            return response()->json([
                'success' => true,
                'hospitalNumber' => $hospitalNumber,
                'password' => $password,
                'followUpDate' => $followUpDate,
                'patient' => $patient,
                'appointment' => $appointment
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Patient registration failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Registration failed: '.$e->getMessage()
            ], 500);
        }
    }

    public function checkMissedAppointments()
    {
        try {
            $now = Carbon::now();
            
            // Only process after 10:00 PM
            if ($now->hour < 22) {
                return response()->json([
                    'success' => true,
                    'message' => "Missed appointments will be processed after 10:00 PM"
                ]);
            }

            $yesterday = Carbon::yesterday();
            
            $missedAppointments = Schedule::where('appointment_date', '<=', $yesterday)
                ->where('checkup_status', 'Pending')
                ->get();
            
            $rescheduledCount = 0;
            
            foreach ($missedAppointments as $appointment) {
                // Calculate new appointment date (next available date)
                $newAppointmentDate = $now->copy()->addDay()->format('Y-m-d');
                
                // Check daily limit
                $dailyCount = Schedule::whereDate('appointment_date', $newAppointmentDate)
                    ->where('confirmation_status', 'confirmed')
                    ->count();
                
                $dailyLimit = 80;
                while ($dailyCount >= $dailyLimit) {
                    $newAppointmentDate = Carbon::parse($newAppointmentDate)->addDay()->format('Y-m-d');
                    $dailyCount = Schedule::whereDate('appointment_date', $newAppointmentDate)
                        ->where('confirmation_status', 'confirmed')
                        ->count();
                }

                // Calculate follow-up date (28 days after new appointment)
                $followUpDate = Carbon::parse($newAppointmentDate)->addDays(28);

                // Update the existing appointment
                $appointment->update([
                    'checkup_status' => 'missed',
                    'checkup_remarks' => 'Patient missed scheduled appointment on ' . 
                        $appointment->appointment_date->format('F j, Y') . 
                        '. Automatically rescheduled to ' . $followUpDate->format('F j, Y'),
                    'appointment_date' => $newAppointmentDate,
                    'new_appointment_date' => $followUpDate,
                    'confirmation_status' => 'confirmed'
                ]);
                
                $rescheduledCount++;
                $this->sendRescheduleNotification($appointment, $newAppointmentDate);
            }
            
            return response()->json([
                'success' => true,
                'message' => "Processed $rescheduledCount missed appointments",
                'rescheduled_count' => $rescheduledCount
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to process missed appointments: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process missed appointments'
            ], 500);
        }
    }
    
    private function sendRescheduleNotification($appointment, $newDate)
    {
        try {
            $user = User::find($appointment->userID);
            $patient = Patient::where('userID', $user->userID)->first();
            
            if ($user && $user->email) {
                $data = [
                    'patientName' => $user->first_name . ' ' . $user->last_name,
                    'hospitalNumber' => $patient->hospitalNumber,
                    'missedDate' => $appointment->appointment_date->format('F j, Y'),
                    'newDate' => $newDate->format('F j, Y'),
                    'reason' => 'Missed scheduled appointment'
                ];
                
                Mail::to($user->email)->send(new \App\Mail\AppointmentRescheduledMail($data));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send reschedule notification: ' . $e->getMessage());
        }
    }

    public function checkTermsStatus(Request $request)
    {
        try {
            $patient = Patient::where('userID', auth()->id())->first();
            
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'AccStatus' => $patient->AccStatus,
                'TermsAndCondition' => $patient->TermsAndCondition
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check terms status'
            ], 500);
        }
    }

    public function acceptTerms(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $patient = Patient::where('userID', auth()->id())->first();
            
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found'
                ], 404);
            }

            $patient->update([
                'AccStatus' => 'active',
                'TermsAndCondition' => 'agreed'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Terms accepted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept terms'
            ], 500);
        }
    }

    public function generateCertificate(Request $request)
    {
        try {
            $patientDetails = $request->input('patientDetails');
            
            // Generate appointment schedule for the certificate
            $initialDate = Carbon::parse($patientDetails['initialAppointment']);
            $followUpDate = Carbon::parse($patientDetails['followUpDate']);
            
            $appointmentSchedule = [];
            $currentDate = $initialDate->copy();
            $endDate = $currentDate->copy()->addMonth();
            
            while ($currentDate <= $endDate) {
                $appointmentSchedule[] = [
                    'date' => $currentDate->format('F j, Y (l)'),
                    'type' => $currentDate->eq($initialDate) ? 'Initial' : 
                            ($currentDate->eq($followUpDate) ? 'Follow-up' : 'Regular Check-up')
                ];
                $currentDate->addDays(28);
            }

            $pdf = PDF::loadView('pdf.patient_info_pdf', [
                'patientName' => $patientDetails['patientName'],
                'hospitalNumber' => $patientDetails['hospitalNumber'],
                'initialAppointment' => $initialDate->format('F j, Y'),
                'followUpAppointment' => $followUpDate->format('F j, Y'),
                'appointmentSchedule' => $appointmentSchedule,
                'generatedDate' => now()->format('F j, Y')
            ]);

            return $pdf->download('patient_info_pdf');

        } catch (\Exception $e) {
            Log::error('Certificate generation failed: '.$e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate certificate'
            ], 500);
        }
    }
}