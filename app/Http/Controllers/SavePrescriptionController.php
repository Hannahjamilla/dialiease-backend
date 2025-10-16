<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Models\PrescriptionMedicine;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Patient;
use App\Models\Queue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use PDF;

class SavePrescriptionController extends Controller
{
    public function savePrescription(Request $request)
    {
        Log::info('=== PRESCRIPTION SAVE REQUEST STARTED ===');
        Log::info('Prescription save request received', $request->all());
        
        DB::beginTransaction();
        
        try {
            $validator = Validator::make($request->all(), [
                'patientID' => 'required|integer|exists:patients,patientID',
                'medicines' => 'required|array|min:1',
                'medicines.*.medicine_id' => 'required|integer|exists:medicines,id',
                'medicines.*.dosage' => 'required|string|max:255',
                'medicines.*.frequency' => 'required|string|max:255',
                'medicines.*.duration' => 'required|string|max:255',
                'medicines.*.instructions' => 'nullable|string',
                'additional_instructions' => 'nullable|string',
                'pd_data' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                Log::error('Validation failed', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed. Please check your input.',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get patient data
            $patient = Patient::with('user')->find($request->patientID);
            if (!$patient) {
                throw new \Exception('Patient not found with ID: ' . $request->patientID);
            }

            if (!$patient->user) {
                throw new \Exception('User record not found for patient ID: ' . $request->patientID);
            }

            Log::info('=== PATIENT VERIFICATION ===');
            Log::info('Patient ID from request: ' . $request->patientID);
            Log::info('Patient database record:', [
                'patientID' => $patient->patientID,
                'userID' => $patient->userID,
                'patient_name' => $patient->user->first_name . ' ' . $patient->user->last_name,
                'patient_email' => $patient->user->email
            ]);

            // Extract PD data
            $pdData = $request->pd_data ?? [];
            
            // Get total exchanges (number of columns/non-empty concentrations)
            $totalExchanges = $pdData['totalExchanges'] ?? 0;
            
            // Get exchanges (solution concentrations) as comma-separated string
            $exchangesString = '';
            if (!empty($pdData['exchanges'])) {
                $exchangesString = is_array($pdData['exchanges']) 
                    ? implode(', ', array_filter($pdData['exchanges'])) 
                    : $pdData['exchanges'];
            }
            
            // Format bag percentages as comma-separated string
            $bagPercentagesString = '';
            if (!empty($pdData['bagPercentages'])) {
                $bagPercentagesString = is_array($pdData['bagPercentages'])
                    ? implode(', ', array_filter($pdData['bagPercentages']))
                    : $pdData['bagPercentages'];
            }
            
            // Format bag counts as comma-separated string
            $bagCountsString = '';
            if (!empty($pdData['bagCounts'])) {
                $bagCountsString = is_array($pdData['bagCounts'])
                    ? implode(', ', array_filter($pdData['bagCounts']))
                    : $pdData['bagCounts'];
            }
            
            // Convert fillVolume and dwellTime to proper format
            $fillVolume = null;
            if (!empty($pdData['fillVolume'])) {
                $fillVolume = $this->formatVolume($pdData['fillVolume']);
            }
            
            $dwellTime = null;
            if (!empty($pdData['dwellTime'])) {
                $dwellTime = $this->formatDwellTime($pdData['dwellTime']);
            }
            
            Log::info('=== PD DATA PROCESSING ===');
            Log::info('PD Data received:', $pdData);
            Log::info('Processed PD values:', [
                'fillVolume' => $fillVolume,
                'dwellTime' => $dwellTime,
                'totalExchanges' => $totalExchanges,
                'exchangesString' => $exchangesString,
                'bagPercentagesString' => $bagPercentagesString,
                'bagCountsString' => $bagCountsString
            ]);

            // Get authenticated user ID (doctor ID) - this is the prescriber
            $doctorId = auth()->check() ? auth()->id() : 1;
            $doctor = User::find($doctorId);
            
            Log::info('=== PRESCRIPTION CREATION DETAILS ===');
            Log::info('Creating prescription with CORRECT IDs:', [
                'patientID (from patients table)' => $patient->patientID,
                'userID (patient user ID from users table)' => $patient->userID,
                'doctor_id (prescriber)' => $doctorId,
                'patient_name' => $patient->user->first_name . ' ' . $patient->user->last_name,
                'doctor_name' => $doctor ? $doctor->first_name . ' ' . $doctor->last_name : 'Unknown'
            ]);

            // Create prescription with CORRECT IDs
            $prescription = Prescription::create([
                'patientID' => $patient->patientID, // patientID from patients table
                'userID' => $patient->userID, // userID from users table for the patient
                'additional_instructions' => $request->additional_instructions,
                'pd_system' => $pdData['system'] ?? null,
                'pd_modality' => $pdData['modality'] ?? null,
                'pd_total_exchanges' => $totalExchanges,
                'pd_fill_volume' => $fillVolume,
                'pd_dwell_time' => $dwellTime,
                'pd_exchanges' => $exchangesString,
                'pd_bag_percentages' => $bagPercentagesString,
                'pd_bag_counts' => $bagCountsString
            ]);

            Log::info('✅ Prescription created with ID: ' . $prescription->id);
            Log::info('✅ CORRECT IDs assigned to prescription:', [
                'prescription_patientID' => $prescription->patientID,
                'prescription_userID' => $prescription->userID,
                'pd_fill_volume' => $prescription->pd_fill_volume,
                'pd_dwell_time' => $prescription->pd_dwell_time
            ]);

            // Create prescription medicines with CORRECT patientID and userID
            $prescriptionMedicines = [];
            foreach ($request->medicines as $medicineData) {
                $prescriptionMedicine = PrescriptionMedicine::create([
                    'prescription_id' => $prescription->id,
                    'patientID' => $patient->patientID, // Use patientID from patients table
                    'userID' => $patient->userID, // Use userID from users table for the patient
                    'medicine_id' => $medicineData['medicine_id'],
                    'dosage' => $medicineData['dosage'],
                    'frequency' => $medicineData['frequency'],
                    'duration' => $medicineData['duration'],
                    'instructions' => $medicineData['instructions'] ?? null
                ]);
                
                $prescriptionMedicines[] = $prescriptionMedicine;
                
                Log::info('✅ Medicine added to prescription with correct IDs:', [
                    'prescription_id' => $prescription->id,
                    'patientID' => $patient->patientID,
                    'userID' => $patient->userID,
                    'medicine_id' => $medicineData['medicine_id']
                ]);
            }

            // Update both schedule and queue status to completed
            $updateResult = $this->updateScheduleAndQueueStatus($patient);

            // Generate PDF and send email
            $emailResult = $this->sendPrescriptionEmail($prescription, $prescriptionMedicines, $patient, $doctor, $pdData);

            DB::commit();
            
            Log::info('=== PRESCRIPTION SAVE COMPLETED SUCCESSFULLY ===');
            Log::info('Final verification - CORRECT IDs assigned:', [
                'prescription_table' => [
                    'patientID' => $prescription->patientID,
                    'userID' => $prescription->userID,
                    'pd_fill_volume' => $prescription->pd_fill_volume,
                    'pd_dwell_time' => $prescription->pd_dwell_time
                ],
                'prescription_medicine_table' => [
                    'patientID' => $patient->patientID,
                    'userID' => $patient->userID,
                    'count' => count($prescriptionMedicines)
                ],
                'patient_info' => [
                    'patientID' => $patient->patientID,
                    'userID' => $patient->userID,
                    'name' => $patient->user->first_name . ' ' . $patient->user->last_name
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Prescription saved successfully and checkup marked as completed',
                'prescription_id' => $prescription->id,
                'status_updates' => $updateResult,
                'email_sent' => $emailResult['success'],
                'email_message' => $emailResult['message'],
                'patient_info' => [
                    'id' => $patient->patientID,
                    'name' => $patient->user->first_name . ' ' . $patient->user->last_name,
                    'email' => $patient->user->email
                ],
                'assigned_ids' => [
                    'prescription' => [
                        'patientID' => $prescription->patientID,
                        'userID' => $prescription->userID
                    ],
                    'prescription_medicines' => [
                        'patientID' => $patient->patientID,
                        'userID' => $patient->userID,
                        'count' => count($prescriptionMedicines)
                    ]
                ],
                'pd_data_saved' => [
                    'fill_volume' => $prescription->pd_fill_volume,
                    'dwell_time' => $prescription->pd_dwell_time,
                    'total_exchanges' => $prescription->pd_total_exchanges
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('❌ Prescription save failed: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save prescription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format volume value for database storage
     */
    private function formatVolume($volume)
    {
        if (empty($volume)) {
            return null;
        }

        // If it's already a number, return as float
        if (is_numeric($volume)) {
            return floatval($volume);
        }

        // If it's a string, try to extract numbers
        if (is_string($volume)) {
            // Remove any non-numeric characters except decimal point
            $cleaned = preg_replace('/[^\d.]/', '', $volume);
            if (is_numeric($cleaned)) {
                return floatval($cleaned);
            }
        }

        return null;
    }

    /**
     * Format dwell time value for database storage
     */
    private function formatDwellTime($dwellTime)
    {
        if (empty($dwellTime)) {
            return null;
        }

        // If it's already a number, return as float
        if (is_numeric($dwellTime)) {
            return floatval($dwellTime);
        }

        // If it's a string, try to extract numbers
        if (is_string($dwellTime)) {
            // Remove any non-numeric characters except decimal point
            $cleaned = preg_replace('/[^\d.]/', '', $dwellTime);
            if (is_numeric($cleaned)) {
                return floatval($cleaned);
            }
            
            // Try to parse time formats like "4 hours", "30 mins", etc.
            if (preg_match('/(\d+(?:\.\d+)?)\s*(hour|hr|h|minute|min|m)/i', $dwellTime, $matches)) {
                $value = floatval($matches[1]);
                $unit = strtolower($matches[2]);
                
                // Convert to hours if in minutes
                if (in_array($unit, ['minute', 'min', 'm'])) {
                    return $value / 60; // Convert minutes to hours
                }
                
                return $value; // Already in hours
            }
        }

        return null;
    }

    /**
     * Update both schedule and queue status to completed for the patient
     */
    private function updateScheduleAndQueueStatus($patient)
    {
        $result = [
            'schedule_updated' => false,
            'queue_updated' => false,
            'schedule_id' => null,
            'queue_id' => null,
            'patient_userID' => $patient->userID,
            'patient_name' => $patient->user->first_name . ' ' . $patient->user->last_name,
            'debug_info' => [],
            'errors' => []
        ];

        try {
            $patientId = $patient->patientID;
            $patientUserID = $patient->userID;

            Log::info('=== STARTING STATUS UPDATE ===');
            Log::info('Updating status for patient:', [
                'patientID' => $patientId,
                'patient_userID' => $patientUserID,
                'patient_name' => $result['patient_name']
            ]);

            // Find queue record
            $userIDsToTry = [$patientUserID, $patientId];
            
            $queueRecord = null;
            $foundUserID = null;

            foreach ($userIDsToTry as $userID) {
                Log::info("🔍 Searching for queue with userID: " . $userID);
                
                $queueRecord = DB::table('queue')
                    ->where('userID', $userID)
                    ->whereIn('status', ['waiting', 'in-progress'])
                    ->orderBy('appointment_date', 'desc')
                    ->first();

                if ($queueRecord) {
                    $foundUserID = $userID;
                    Log::info("✅ FOUND queue with userID: " . $userID);
                    break;
                }
            }

            if (!$queueRecord) {
                Log::error('❌ No active queue found for any userID. Tried: ' . implode(', ', $userIDsToTry));
                $result['errors'][] = 'No active queue found for this patient';
                return $result;
            }

            Log::info('✅ QUEUE RECORD FOUND:', [
                'queue_id' => $queueRecord->queue_id,
                'schedule_id' => $queueRecord->schedule_id,
                'userID' => $queueRecord->userID,
                'appointment_date' => $queueRecord->appointment_date,
                'status' => $queueRecord->status,
                'checkup_status' => $queueRecord->checkup_status,
                'found_using_userID' => $foundUserID
            ]);

            // Find the schedule
            $schedule = null;
            
            if ($queueRecord->schedule_id) {
                $schedule = Schedule::where('schedule_id', $queueRecord->schedule_id)->first();
                if ($schedule) {
                    Log::info('✅ SCHEDULE FOUND using queue schedule_id: ' . $queueRecord->schedule_id);
                }
            }

            if (!$schedule) {
                $schedule = Schedule::where('patient_id', $patientId)
                    ->where(function($query) {
                        $query->where('checkup_status', 'Pending')
                              ->orWhere('checkup_status', 'pending');
                    })
                    ->orderBy('appointment_date', 'desc')
                    ->first();
                if ($schedule) {
                    Log::info('✅ SCHEDULE FOUND using patient_id: ' . $patientId);
                }
            }

            if (!$schedule) {
                $schedule = Schedule::where('userID', $queueRecord->userID)
                    ->where('appointment_date', $queueRecord->appointment_date)
                    ->first();
                if ($schedule) {
                    Log::info('✅ SCHEDULE FOUND using queue userID + date');
                }
            }

            if ($schedule) {
                Log::info('✅ CORRECT SCHEDULE IDENTIFIED:', [
                    'schedule_id' => $schedule->schedule_id,
                    'patient_id' => $schedule->patient_id,
                    'userID' => $schedule->userID,
                    'appointment_date' => $schedule->appointment_date,
                    'current_checkup_status' => $schedule->checkup_status
                ]);

                // UPDATE SCHEDULE
                $originalScheduleStatus = $schedule->checkup_status;
                $schedule->checkup_status = 'Completed';
                $schedule->checkup_remarks = 'Prescription completed on ' . Carbon::now()->format('Y-m-d H:i:s');
                
                if ($schedule->save()) {
                    $result['schedule_updated'] = true;
                    $result['schedule_id'] = $schedule->schedule_id;
                    Log::info('✅ Schedule updated from "' . $originalScheduleStatus . '" to "Completed"');
                } else {
                    Log::error('❌ Failed to save schedule update');
                    $result['errors'][] = 'Failed to update schedule';
                }

                // UPDATE THE QUEUE
                Log::info('🔄 Updating queue with queue_id: ' . $queueRecord->queue_id);
                
                $rowsAffected = DB::table('queue')
                    ->where('queue_id', $queueRecord->queue_id)
                    ->update([
                        'status' => 'completed',
                        'checkup_status' => 'Completed',
                        'updated_at' => Carbon::now()
                    ]);

                if ($rowsAffected > 0) {
                    $result['queue_updated'] = true;
                    $result['queue_id'] = $queueRecord->queue_id;
                    Log::info('✅ Queue updated successfully, rows affected: ' . $rowsAffected);
                    
                    $updatedQueue = DB::table('queue')->where('queue_id', $queueRecord->queue_id)->first();
                    Log::info('✅ Queue update verified:', [
                        'queue_id' => $updatedQueue->queue_id,
                        'new_status' => $updatedQueue->status,
                        'new_checkup_status' => $updatedQueue->checkup_status
                    ]);
                } else {
                    Log::error('❌ Failed to update queue');
                    $result['errors'][] = 'Failed to update queue';
                }

            } else {
                Log::warning('❌ No schedule found for patient');
                $result['errors'][] = 'No schedule found for this patient';
            }

            Log::info('=== STATUS UPDATE COMPLETED ===', $result);

        } catch (\Exception $e) {
            Log::error('❌ Error in updateScheduleAndQueueStatus: ' . $e->getMessage());
            $result['errors'][] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Generate PDF and send email to patient
     */
    private function sendPrescriptionEmail($prescription, $prescriptionMedicines, $patient, $doctor, $pdData)
    {
        $result = ['success' => false, 'message' => ''];
        
        try {
            $patientEmail = $patient->user->email;
            
            if (!$patientEmail) {
                $message = 'Patient email not found, skipping email sending';
                Log::warning($message);
                $result['message'] = $message;
                return $result;
            }

            Log::info('Sending prescription email to verified patient:', [
                'patient_id' => $patient->patientID,
                'patient_name' => $patient->user->first_name . ' ' . $patient->user->last_name,
                'patient_email' => $patientEmail,
                'doctor_name' => $doctor->first_name . ' ' . $doctor->last_name
            ]);

            $data = [
                'prescription' => $prescription,
                'medicines' => $prescriptionMedicines,
                'patient' => $patient,
                'doctor' => $doctor,
                'pdData' => $pdData,
                'date' => Carbon::now()->format('F j, Y'),
                'time' => Carbon::now()->format('g:i A')
            ];

            // Generate PDF
            $pdf = PDF::loadView('prescriptions.pdf', $data);
            
            // Send email
            Mail::send('emails.prescription', $data, function($message) use ($patient, $patientEmail, $pdf, $doctor) {
                $message->to($patientEmail)
                        ->subject('Your Prescription from ' . $doctor->first_name . ' ' . $doctor->last_name)
                        ->attachData($pdf->output(), 
                                   'prescription_' . $patient->patientID . '_' . date('Y-m-d') . '.pdf', 
                                   ['mime' => 'application/pdf']);
            });

            Log::info('✅ Prescription email sent to: ' . $patientEmail);
            $result['success'] = true;
            $result['message'] = 'Email sent successfully to ' . $patientEmail;
            
        } catch (\Exception $e) {
            Log::error('Failed to send prescription email: ' . $e->getMessage());
            Log::error('Email error details:', [
                'patient_id' => $patient->patientID,
                'patient_name' => $patient->user->first_name . ' ' . $patient->user->last_name,
                'patient_email' => $patient->user->email ?? 'not found',
                'error' => $e->getMessage()
            ]);
            
            $result['message'] = 'Email sending failed: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Get patient prescription medicines
     */
    public function getPatientPrescriptionMedicines(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'patientID' => 'required|integer|exists:patients,patientID'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $patientID = $request->patientID;

            $prescriptionMedicines = PrescriptionMedicine::with(['medicine', 'prescription'])
                ->where('patientID', $patientID)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $prescriptionMedicines,
                'count' => $prescriptionMedicines->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get patient prescription medicines failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch prescription medicines'
            ], 500);
        }
    }

    /**
     * Get user prescription medicines (for patients to view their own medicines)
     */
    public function getUserPrescriptionMedicines(Request $request)
    {
        try {
            $userID = auth()->id();

            if (!$userID) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $patient = Patient::where('userID', $userID)->first();
            
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient record not found for this user'
                ], 404);
            }

            $prescriptionMedicines = PrescriptionMedicine::with(['medicine', 'prescription'])
                ->where('userID', $userID)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $prescriptionMedicines,
                'count' => $prescriptionMedicines->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get user prescription medicines failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch prescription medicines'
            ], 500);
        }
    }

    public function getPatientPrescriptions($patientId)
    {
        try {
            Log::info('Fetching prescriptions for patient ID: ' . $patientId);

            $patient = Patient::with('user')->find($patientId);
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            $prescriptions = Prescription::with([
                'medicines.medicine',
                'user'
            ])
            ->where('patientID', $patientId)
            ->orderBy('created_at', 'desc')
            ->get();

            Log::info('Found ' . $prescriptions->count() . ' prescriptions for patient');

            return response()->json([
                'success' => true,
                'data' => $prescriptions,
                'patient' => $patient
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching patient prescriptions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch prescriptions'
            ], 500);
        }
    }

    public function getPrescriptionDetails($prescriptionId)
    {
        try {
            $prescription = Prescription::with([
                'medicines.medicine',
                'patient.user',
                'user'
            ])->find($prescriptionId);

            if (!$prescription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prescription not found'
                ], 404);
            }

            Log::info('Prescription details loaded:', [
                'prescription_id' => $prescription->id,
                'medicines_count' => $prescription->medicines->count(),
                'patient_id' => $prescription->patientID,
                'doctor_id' => $prescription->userID,
                'pd_fill_volume' => $prescription->pd_fill_volume,
                'pd_dwell_time' => $prescription->pd_dwell_time
            ]);

            return response()->json([
                'success' => true,
                'data' => $prescription
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching prescription details: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch prescription details'
            ], 500);
        }
    }

    public function downloadPrescriptionPDF($prescriptionId)
    {
        try {
            $prescription = Prescription::with([
                'medicines.medicine',
                'patient.user',
                'user'
            ])->find($prescriptionId);

            if (!$prescription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Prescription not found'
                ], 404);
            }

            $data = [
                'prescription' => $prescription,
                'medicines' => $prescription->medicines,
                'patient' => $prescription->patient,
                'doctor' => $prescription->user,
                'date' => Carbon::now()->format('F j, Y'),
                'time' => Carbon::now()->format('g:i A')
            ];

            $pdf = PDF::loadView('prescriptions.pdf', $data);

            return $pdf->download('prescription-' . $prescriptionId . '.pdf');

        } catch (\Exception $e) {
            Log::error('Error generating prescription PDF: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF'
            ], 500);
        }
    }

    /**
     * Get all patient prescriptions for doctor's view
     */
    public function getAllPatientPrescriptions(Request $request)
    {
        try {
            $search = $request->query('search', '');
            $dateFrom = $request->query('date_from', '');
            $dateTo = $request->query('date_to', '');
            $system = $request->query('system', 'all');

            Log::info('Fetching all patient prescriptions with filters:', [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'system' => $system
            ]);

            $patientsQuery = Patient::with(['user', 'prescriptions.medicines.medicine'])
                ->whereHas('user', function($query) {
                    $query->where('userLevel', 'patient');
                });

            if (!empty($search) && strlen($search) >= 2) {
                $patientsQuery->whereHas('user', function($query) use ($search) {
                    $query->where(DB::raw("CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name)"), 'LIKE', "%{$search}%")
                          ->orWhere('hospitalNumber', 'LIKE', "%{$search}%")
                          ->orWhere('legalRepresentative', 'LIKE', "%{$search}%");
                });
            }

            $patients = $patientsQuery->get();

            Log::info('Found ' . $patients->count() . ' patients');

            $patientsWithPrescriptions = [];
            
            foreach ($patients as $patient) {
                $patientPrescriptions = [];
                
                foreach ($patient->prescriptions as $prescription) {
                    if (!empty($dateFrom)) {
                        $prescriptionDate = Carbon::parse($prescription->created_at);
                        $filterDateFrom = Carbon::parse($dateFrom);
                        if ($prescriptionDate->lt($filterDateFrom)) {
                            continue;
                        }
                    }

                    if (!empty($dateTo)) {
                        $prescriptionDate = Carbon::parse($prescription->created_at);
                        $filterDateTo = Carbon::parse($dateTo);
                        if ($prescriptionDate->gt($filterDateTo)) {
                            continue;
                        }
                    }

                    if ($system !== 'all' && $prescription->pd_system !== $system) {
                        continue;
                    }

                    $medicines = [];
                    foreach ($prescription->medicines as $prescriptionMedicine) {
                        $medicines[] = [
                            'medicine_name' => $prescriptionMedicine->medicine->name ?? 'Unknown Medicine',
                            'dosage' => $prescriptionMedicine->dosage,
                            'frequency' => $prescriptionMedicine->frequency,
                            'duration' => $prescriptionMedicine->duration,
                            'instructions' => $prescriptionMedicine->instructions
                        ];
                    }

                    $patientPrescriptions[] = [
                        'id' => $prescription->id,
                        'userID' => $prescription->userID,
                        'additional_instructions' => $prescription->additional_instructions,
                        'pd_system' => $prescription->pd_system,
                        'pd_modality' => $prescription->pd_modality,
                        'pd_total_exchanges' => $prescription->pd_total_exchanges,
                        'pd_fill_volume' => $prescription->pd_fill_volume,
                        'pd_dwell_time' => $prescription->pd_dwell_time,
                        'pd_exchanges' => $prescription->pd_exchanges,
                        'pd_bag_percentages' => $prescription->pd_bag_percentages,
                        'pd_bag_counts' => $prescription->pd_bag_counts,
                        'created_at' => $prescription->created_at,
                        'updated_at' => $prescription->updated_at,
                        'medicines' => $medicines
                    ];
                }

                if (!empty($patientPrescriptions) || (empty($dateFrom) && empty($dateTo) && $system === 'all')) {
                    $patientsWithPrescriptions[] = [
                        'patientID' => $patient->patientID,
                        'name' => trim($patient->user->first_name . ' ' . ($patient->user->middle_name ? $patient->user->middle_name . ' ' : '') . $patient->user->last_name),
                        'hospitalNumber' => $patient->hospitalNumber,
                        'date_of_birth' => $patient->user->date_of_birth,
                        'gender' => $patient->user->gender,
                        'email' => $patient->user->email,
                        'legalRepresentative' => $patient->legalRepresentative,
                        'situationStatus' => $patient->situationStatus,
                        'prescriptions' => $patientPrescriptions
                    ];
                }
            }

            Log::info('Returning ' . count($patientsWithPrescriptions) . ' patients with prescriptions');

            return response()->json([
                'success' => true,
                'prescriptions' => $patientsWithPrescriptions
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching all patient prescriptions: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch prescriptions: ' . $e->getMessage()
            ], 500);
        }
    }
}