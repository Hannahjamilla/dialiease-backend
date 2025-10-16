<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Patient;
use App\Models\Schedule;
use App\Models\Prescription;
use App\Models\Medicine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\PrescriptionEmail;
use PDF;

class DoctorCheckupController extends Controller
{
    public function getCheckupData()
    {
        try {
            $doctorId = Auth::id();
            
            if (!$doctorId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required. Please login again.',
                    'error_code' => 'AUTH_REQUIRED'
                ], 401);
            }

            $today = Carbon::today()->toDateString();

            $baseQuery = Schedule::with(['patient.user'])
                ->whereDate('appointment_date', $today)
                ->where('userID', $doctorId)
                ->where('confirmation_status', 'confirmed');

            $allPatientsToday = $baseQuery->get()
                ->map(function ($schedule) {
                    if (!$schedule->patient || !$schedule->patient->user) {
                        return null;
                    }
                    
                    return [
                        'patientID' => $schedule->patient_id,
                        'first_name' => $schedule->patient->user->first_name ?? 'Unknown',
                        'last_name' => $schedule->patient->user->last_name ?? 'Unknown',
                        'hospitalNumber' => $schedule->patient->hospitalNumber ?? 'N/A',
                        'schedule_id' => $schedule->schedule_id,
                        'status' => $schedule->checkup_status,
                        'appointment_time' => $schedule->appointment_date,
                        'confirmation_status' => $schedule->confirmation_status
                    ];
                })->filter()->values();

            // Group patients by status
            $queuePatients = $allPatientsToday->filter(fn($p) => $p['status'] === 'Pending');
            $currentPatients = $allPatientsToday->filter(fn($p) => $p['status'] === 'In Progress');
            $completedPatients = $allPatientsToday->filter(fn($p) => $p['status'] === 'Completed');

            return response()->json([
                'success' => true,
                'data' => [
                    'allPatientsToday' => $allPatientsToday,
                    'queuePatients' => $queuePatients,
                    'currentPatients' => $currentPatients,
                    'completedPatients' => $completedPatients
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Checkup data fetch error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch checkup data.',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function startCheckup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patientId' => 'required|integer|exists:patients,patientID',
            'scheduleId' => 'required|integer|exists:schedules,schedule_id'
        ], [
            'patientId.required' => 'Patient ID is required.',
            'patientId.integer' => 'Patient ID must be a valid number.',
            'patientId.exists' => 'The specified patient does not exist.',
            'scheduleId.required' => 'Schedule ID is required.',
            'scheduleId.integer' => 'Schedule ID must be a valid number.',
            'scheduleId.exists' => 'The specified schedule does not exist.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred.',
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $doctorId = Auth::id();
            if (!$doctorId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your session has expired. Please login again.',
                    'error_code' => 'SESSION_EXPIRED'
                ], 401);
            }

            $schedule = Schedule::where('schedule_id', $request->scheduleId)
                ->where('patient_id', $request->patientId)
                ->where('userID', $doctorId)
                ->first();

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either the schedule does not exist or you are not authorized to access it.',
                    'error_code' => 'SCHEDULE_NOT_FOUND'
                ], 404);
            }

            if ($schedule->checkup_status === 'In Progress') {
                return response()->json([
                    'success' => false,
                    'message' => 'Checkup is already in progress for this patient.',
                    'error_code' => 'CHECKUP_ALREADY_STARTED'
                ], 400);
            }

            if ($schedule->checkup_status === 'Completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Checkup has already been completed for this patient.',
                    'error_code' => 'CHECKUP_ALREADY_COMPLETED'
                ], 400);
            }

            $schedule->checkup_status = 'In Progress';
            $schedule->save();

            return response()->json([
                'success' => true,
                'message' => 'Checkup started successfully.',
                'data' => [
                    'schedule_id' => $schedule->schedule_id,
                    'new_status' => $schedule->checkup_status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Start checkup error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to start checkup due to a server error.',
                'error_code' => 'SERVER_ERROR',
                'error_details' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function completeCheckup(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'patientId' => 'required|integer|exists:patients,patientID',
            'scheduleId' => 'required|integer|exists:schedules,schedule_id'
        ], [
            'patientId.required' => 'Patient ID is required.',
            'patientId.integer' => 'Patient ID must be a valid number.',
            'patientId.exists' => 'The specified patient does not exist.',
            'scheduleId.required' => 'Schedule ID is required.',
            'scheduleId.integer' => 'Schedule ID must be a valid number.',
            'scheduleId.exists' => 'The specified schedule does not exist.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors occurred.',
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $doctorId = Auth::id();
            if (!$doctorId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your session has expired. Please login again.',
                    'error_code' => 'SESSION_EXPIRED'
                ], 401);
            }

            $schedule = Schedule::where('schedule_id', $request->scheduleId)
                ->where('patient_id', $request->patientId)
                ->where('userID', $doctorId)
                ->first();

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either the schedule does not exist or you are not authorized to access it.',
                    'error_code' => 'SCHEDULE_NOT_FOUND'
                ], 404);
            }

            if ($schedule->checkup_status === 'Completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Checkup has already been completed for this patient.',
                    'error_code' => 'CHECKUP_ALREADY_COMPLETED'
                ], 400);
            }

            if ($schedule->checkup_status === 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Checkup must be started before it can be completed.',
                    'error_code' => 'CHECKUP_NOT_STARTED'
                ], 400);
            }

            $schedule->checkup_status = 'Completed';
            $schedule->save();

            return response()->json([
                'success' => true,
                'message' => 'Checkup completed successfully.',
                'data' => [
                    'schedule_id' => $schedule->schedule_id,
                    'new_status' => $schedule->checkup_status
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Complete checkup error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete checkup due to a server error.',
                'error_code' => 'SERVER_ERROR',
                'error_details' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    public function getPatientPrescriptions($patientId)
    {
        try {
            if (!Patient::where('patientID', $patientId)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found in our records.',
                    'error_code' => 'PATIENT_NOT_FOUND'
                ], 404);
            }

            $prescriptions = Prescription::where('patientID', $patientId)
                ->with(['doctor.user', 'medicines'])
                ->orderBy('prescription_date', 'desc')
                ->get()
                ->map(function ($prescription) {
                    if (!$prescription->doctor || !$prescription->doctor->user) {
                        Log::warning('Incomplete doctor data for prescription ID: ' . $prescription->id);
                        return null;
                    }

                    return [
                        'id' => $prescription->id,
                        'date' => $prescription->prescription_date->format('Y-m-d H:i'),
                        'doctor' => $prescription->doctor->user->name ?? 'Unknown Doctor',
                        'medicines' => $prescription->medicines->map(function ($medicine) {
                            return [
                                'id' => $medicine->id,
                                'name' => $medicine->name,
                                'dosage' => $medicine->pivot->dosage,
                                'frequency' => $medicine->pivot->frequency,
                                'duration' => $medicine->pivot->duration,
                                'instructions' => $medicine->pivot->instructions
                            ];
                        }),
                        'additional_instructions' => $prescription->additional_instructions
                    ];
                })->filter()->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'prescriptions' => $prescriptions,
                    'count' => $prescriptions->count()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get prescriptions error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve prescriptions due to a server error.',
                'error_code' => 'SERVER_ERROR',
                'error_details' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

public function submitPrescription(Request $request)
{
    $validator = Validator::make($request->all(), [
        'patientId' => 'required|integer|exists:patients,patientID',
        'medicines' => 'required|array|min:1',
        'medicines.*.id' => 'required|integer|exists:medicines,id',
        'medicines.*.dosage' => 'required|string|max:255',
        'medicines.*.frequency' => 'required|string|max:255',
        'medicines.*.duration' => 'required|string|max:255',
        'medicines.*.instructions' => 'nullable|string',
        'instructions' => 'nullable|string'
    ], [
        'patientId.required' => 'Patient ID is required.',
        'patientId.integer' => 'Patient ID must be a valid number.',
        'patientId.exists' => 'The specified patient does not exist.',
        'medicines.required' => 'At least one medicine is required.',
        'medicines.array' => 'Medicines must be provided as an array.',
        'medicines.min' => 'At least one medicine is required.',
        'medicines.*.id.required' => 'Medicine ID is required.',
        'medicines.*.id.integer' => 'Medicine ID must be a valid number.',
        'medicines.*.id.exists' => 'The specified medicine does not exist.',
        'medicines.*.dosage.required' => 'Dosage is required for each medicine.',
        'medicines.*.frequency.required' => 'Frequency is required for each medicine.',
        'medicines.*.duration.required' => 'Duration is required for each medicine.'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Prescription validation failed.',
            'error_code' => 'VALIDATION_ERROR',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $doctorId = Auth::id();
        if (!$doctorId) {
            return response()->json([
                'success' => false,
                'message' => 'Your session has expired. Please login again.',
                'error_code' => 'SESSION_EXPIRED'
            ], 401);
        }

        $validated = $validator->validated();

        // Create the prescription first (save to database)
        $prescription = Prescription::create([
            'userID' => $doctorId,
            'patientID' => $validated['patientId'],
            'prescription_date' => now(),
            'additional_instructions' => $validated['instructions'] ?? null
        ]);

        $medicineData = [];
        foreach ($validated['medicines'] as $medicine) {
            $medicineData[$medicine['id']] = [
                'dosage' => $medicine['dosage'],
                'frequency' => $medicine['frequency'],
                'duration' => $medicine['duration'],
                'instructions' => $medicine['instructions'] ?? null
            ];
        }

        $prescription->medicines()->attach($medicineData);

        // Generate PDF
        $pdf = $this->generatePrescriptionPdf($prescription);
        $fileName = 'prescription_' . $prescription->id . '_' . time() . '.pdf';
        $pdfPath = storage_path('app/public/prescriptions/' . $fileName);
        
        // Ensure directory exists
        if (!file_exists(dirname($pdfPath))) {
            mkdir(dirname($pdfPath), 0755, true);
        }
        
        $pdf->save($pdfPath);
        
        // Update prescription with file path (save to database)
        $prescription->update([
            'prescription_file' => 'prescriptions/' . $fileName,
            'prescription_text' => $this->generatePrescriptionText($prescription)
        ]);

        // Send email to patient (this should work independently of database save)
        $emailSent = false;
        try {
            $emailSent = $this->sendPrescriptionEmail($prescription, $pdfPath);
        } catch (\Exception $emailException) {
            Log::error('Email sending failed but prescription was saved: ' . $emailException->getMessage());
            // Continue even if email fails - prescription is already saved
        }

        return response()->json([
            'success' => true,
            'message' => $emailSent 
                ? 'Prescription submitted successfully and emailed to patient.'
                : 'Prescription submitted successfully but email could not be sent.',
            'data' => [
                'prescription_id' => $prescription->id,
                'medicines_count' => count($medicineData),
                'pdf_url' => asset('storage/prescriptions/' . $fileName),
                'email_sent' => $emailSent
            ]
        ]);

    } catch (\Exception $e) {
        Log::error('Submit prescription error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        return response()->json([
            'success' => false,
            'message' => 'Failed to submit prescription due to a server error.',
            'error_code' => 'SERVER_ERROR',
            'error_details' => env('APP_DEBUG') ? $e->getMessage() : null
        ], 500);
    }
}

    private function generatePrescriptionPdf($prescription)
    {
        $data = [
            'prescription' => $prescription,
            'doctor' => $prescription->doctor->user,
            'patient' => $prescription->patient,
            'date' => $prescription->prescription_date->format('F j, Y'),
            'medicines' => $prescription->medicines
        ];
        
        $pdf = PDF::loadView('prescriptions.pdf', $data);
        $pdf->setPaper('a4', 'portrait');
        
        return $pdf;
    }
    
    private function generatePrescriptionText($prescription)
    {
        $text = "Prescription #{$prescription->id}\n";
        $text .= "Date: " . $prescription->prescription_date->format('F j, Y') . "\n";
        $text .= "Patient: {$prescription->patient->user->first_name} {$prescription->patient->user->last_name}\n";
        $text .= "HN: {$prescription->patient->hospitalNumber}\n\n";
        $text .= "Medications:\n";
        
        foreach ($prescription->medicines as $medicine) {
            $text .= "- {$medicine->name}: {$medicine->pivot->dosage}, {$medicine->pivot->frequency} for {$medicine->pivot->duration}";
            if ($medicine->pivot->instructions) {
                $text .= " ({$medicine->pivot->instructions})";
            }
            $text .= "\n";
        }
        
        if ($prescription->additional_instructions) {
            $text .= "\nAdditional Instructions:\n";
            $text .= $prescription->additional_instructions . "\n";
        }
        
        $text .= "\nPrescribed by:\n";
        $text .= "Dr. {$prescription->doctor->user->first_name} {$prescription->doctor->user->last_name}\n";
        
        return $text;
    }

    private function sendPrescriptionEmail($prescription, $pdfPath)
{
    try {
        // Get patient with user relationship to access email
        $patient = Patient::with('user')->find($prescription->patientID);
        
        if (!$patient) {
            Log::error('Patient not found for prescription ID: ' . $prescription->id);
            return false;
        }
        
        if (!$patient->user) {
            Log::error('User record not found for patient ID: ' . $prescription->patientID);
            return false;
        }
        
        if (empty($patient->user->email)) {
            Log::error('Email not found for user ID: ' . $patient->user->userID);
            return false;
        }
        
        $patientEmail = $patient->user->email;
        $patientName = $patient->user->first_name . ' ' . $patient->user->last_name;
        
        // Check if PDF file exists
        if (!file_exists($pdfPath)) {
            Log::error('PDF file not found at path: ' . $pdfPath);
            return false;
        }
        
        Log::info('Attempting to send email to: ' . $patientEmail . ' for prescription ID: ' . $prescription->id);
        
        // Send email with PDF attachment
        Mail::to($patientEmail)->send(new PrescriptionEmail(
            $prescription,
            $pdfPath,
            $patientName
        ));
        
        // Check if email was sent successfully
        if (count(Mail::failures()) > 0) {
            Log::error('Failed to send prescription email to: ' . $patientEmail . '. Failures: ' . json_encode(Mail::failures()));
            return false;
        }
        
        Log::info('Prescription email sent successfully to: ' . $patientEmail);
        return true;
        
    } catch (\Exception $e) {
        Log::error('Failed to send prescription email: ' . $e->getMessage() . 
                  "\nTrace: " . $e->getTraceAsString());
        return false;
    }
}


}