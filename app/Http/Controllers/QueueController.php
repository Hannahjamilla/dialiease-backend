<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Models\Queue;
use App\Models\User;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\Outsolution;
use App\Models\Insolution;

class QueueController extends Controller
{
    public function getTodayQueues()
    {
        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $today = Carbon::today()->format('Y-m-d');
        
        $queues = Queue::with(['user', 'patient.user'])
            ->whereDate('appointment_date', $today)
            ->whereNotNull('queue_number')
            ->where('checkup_status', '!=', 'Completed')
            ->orderBy('emergency_status', 'desc')
            ->orderBy('emergency_priority', 'desc')
            ->orderBy('queue_number', 'asc')
            ->get()
            ->map(function ($queue) {
                $patientName = 'Unknown';
                $hospitalNumber = 'N/A';
                
                if ($queue->patient && $queue->patient->user) {
                    $patientName = $queue->patient->user->first_name . ' ' . $queue->patient->user->last_name;
                    $hospitalNumber = $queue->patient->hospitalNumber;
                } elseif ($queue->user) {
                    $patientName = $queue->user->first_name . ' ' . $queue->user->last_name;
                    $patient = Patient::where('userID', $queue->userID)->first();
                    if ($patient) {
                        $hospitalNumber = $patient->hospitalNumber;
                    }
                }
                
                return [
                    'queue_id' => $queue->queue_id,
                    'queue_number' => $queue->queue_number,
                    'patient_name' => $patientName,
                    'hospital_number' => $hospitalNumber,
                    'status' => $queue->status,
                    'emergency_status' => (bool)$queue->emergency_status,
                    'emergency_priority' => $queue->emergency_priority,
                    'emergency_note' => $queue->emergency_note,
                    'start_time' => $queue->start_time,
                    'appointment_date' => $queue->appointment_date,
                    'doctor_id' => $queue->doctor_id,
                    'checkup_status' => $queue->checkup_status,
                    'userID' => $queue->userID
                ];
            });

        return response()->json([
            'queues' => $queues,
            'current_date' => $today
        ]);
    }

    public function updateQueueStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'queue_id' => 'required|integer|exists:queue,queue_id',
            'status' => 'required|string|in:pending,waiting,in-progress,completed,cancelled',
            'doctor_id' => 'nullable|integer|exists:users,userID,userLevel,doctor',
            'checkup_status' => 'nullable|string|in:Pending,In Progress,Completed,Cancelled'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $queue = Queue::find($request->queue_id);
        
        if (!$queue) {
            return response()->json(['error' => 'Queue not found'], 404);
        }

        // Validate status transition
        $validTransitions = [
            'waiting' => ['in-progress', 'cancelled'],
            'in-progress' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => ['waiting']
        ];

        if (isset($validTransitions[$queue->status]) && 
            !in_array($request->status, $validTransitions[$queue->status])) {
            return response()->json([
                'error' => 'Invalid status transition from ' . $queue->status . ' to ' . $request->status
            ], 400);
        }

        // Set start time if status is changing to in-progress
        if ($request->status === 'in-progress' && $queue->status !== 'in-progress') {
            $queue->start_time = Carbon::now();
        }

        // Update doctor assignment if provided
        if ($request->has('doctor_id') && $request->doctor_id !== null) {
            $doctor = User::where('userID', $request->doctor_id)
                ->where('userLevel', 'doctor')
                ->where('TodaysStatus', 'in duty')
                ->first();
                
            if (!$doctor) {
                return response()->json([
                    'error' => 'Doctor not found or not on duty'
                ], 404);
            }
            
            // Check if doctor is already assigned to another in-progress patient
            $existingAssignment = Queue::where('doctor_id', $request->doctor_id)
                ->where('status', 'in-progress')
                ->whereDate('appointment_date', Carbon::today())
                ->where('queue_id', '!=', $queue->queue_id)
                ->exists();
                
            if ($existingAssignment) {
                return response()->json([
                    'error' => 'Doctor is already assigned to another patient'
                ], 400);
            }
            
            $queue->doctor_id = $request->doctor_id;
        }

        // Update checkup_status if provided
        if ($request->has('checkup_status')) {
            $queue->checkup_status = $request->checkup_status;
        }

        $queue->status = $request->status;
        $queue->save();

        return response()->json([
            'success' => true,
            'message' => 'Queue status updated successfully',
            'queue' => $queue
        ]);
    }

    public function skipQueue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'queue_id' => 'required|integer|exists:queue,queue_id',
            'positions' => 'required|integer|min:1|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            $queue = Queue::find($request->queue_id);
            
            if (!$queue) {
                return response()->json(['error' => 'Queue not found'], 404);
            }

            if ($queue->status !== 'waiting') {
                return response()->json([
                    'error' => 'Only waiting queues can be skipped'
                ], 400);
            }

            $today = Carbon::today()->format('Y-m-d');
            
            // Get the current queue number
            $currentQueueNumber = $queue->queue_number;
            
            // Find the total number of waiting patients
            $totalWaiting = Queue::whereDate('appointment_date', $today)
                ->where('status', 'waiting')
                ->count();
                
            if ($totalWaiting <= 1) {
                return response()->json([
                    'error' => 'Cannot skip when there is only one patient in queue'
                ], 400);
            }

            // Calculate new position (ensure it doesn't go beyond the last position)
            $newPosition = min($currentQueueNumber + $request->positions, $totalWaiting);
            
            // If the patient would be moved to the same or lower position, move to the end
            if ($newPosition <= $currentQueueNumber) {
                $newPosition = $totalWaiting;
            }

            // Update the queue number
            $queue->queue_number = $newPosition;
            $queue->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Queue position updated successfully',
                'new_position' => $newPosition,
                'queue' => $queue
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error skipping queue: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to skip queue: ' . $e->getMessage()
            ], 500);
        }
    }

    public function startQueue(Request $request)
    {
        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            // Get available doctors (doctors without current in-progress patients)
            $availableDoctors = User::where('userLevel', 'doctor')
                ->where('TodaysStatus', 'in duty')
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('queue')
                        ->whereRaw('queue.doctor_id = users.userID')
                        ->where('queue.status', 'in-progress')
                        ->whereDate('queue.appointment_date', Carbon::today());
                })
                ->get();

            if ($availableDoctors->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'error' => 'No available doctors. All doctors are currently busy with patients.'
                ], 400);
            }

            // Get today's waiting queues, prioritizing emergency cases
            $today = Carbon::today()->format('Y-m-d');
            $waitingQueues = Queue::whereDate('appointment_date', $today)
                ->where('status', 'waiting')
                ->orderBy('emergency_status', 'desc')
                ->orderBy('emergency_priority', 'desc')
                ->orderBy('queue_number', 'asc')
                ->get();

            // Check if there are any waiting patients
            if ($waitingQueues->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'error' => 'No patients waiting in queue.'
                ], 400);
            }

            // Assign patients to available doctors (limited by number of available doctors)
            $assignedCount = 0;
            $availableDoctorsCount = count($availableDoctors);
            
            foreach ($waitingQueues as $index => $queue) {
                if ($index < $availableDoctorsCount) {
                    $queue->status = 'in-progress';
                    $queue->doctor_id = $availableDoctors[$index]->userID;
                    $queue->start_time = Carbon::now();
                    $queue->save();
                    $assignedCount++;
                } else {
                    break; // No more doctors available
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Started $assignedCount patient(s) in consultation",
                'assigned_count' => $assignedCount,
                'available_doctors' => $availableDoctorsCount
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error starting queue: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'error' => 'Failed to start queue: ' . $e->getMessage()
            ], 500);
        }
    }

    public function addToQueue(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userID' => 'required|integer|exists:users,userID',
            'appointment_date' => 'required|date|after_or_equal:today'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Verify the user is a patient
        $patientUser = User::where('userID', $request->userID)
            ->where('userLevel', 'patient')
            ->first();
            
        if (!$patientUser) {
            return response()->json([
                'error' => 'User is not a patient or does not exist'
            ], 400);
        }

        // Check if patient already has a queue for today
        $existingQueue = Queue::where('userID', $request->userID)
            ->whereDate('appointment_date', $request->appointment_date)
            ->first();

        if ($existingQueue) {
            return response()->json([
                'error' => 'Patient already has a queue number for this date'
            ], 400);
        }

        // Automatically determine emergency status and priority
        $emergencyData = $this->checkEmergencyStatus($request->userID);
        $emergencyStatus = $emergencyData['is_emergency'];
        $emergencyPriority = $emergencyData['priority'];
        $emergencyNote = $emergencyData['note'];

        // Get the next queue number for the day
        $lastQueue = Queue::whereDate('appointment_date', $request->appointment_date)
            ->orderBy('queue_number', 'desc')
            ->first();

        $nextQueueNumber = $lastQueue ? $lastQueue->queue_number + 1 : 1;

        $queue = Queue::create([
            'userID' => $request->userID,
            'queue_number' => $nextQueueNumber,
            'appointment_date' => $request->appointment_date,
            'status' => 'waiting',
            'emergency_status' => $emergencyStatus,
            'emergency_priority' => $emergencyPriority,
            'emergency_note' => $emergencyNote,
            'checkup_status' => 'Pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Patient added to queue successfully',
            'queue' => $queue,
            'queue_number' => $nextQueueNumber,
            'emergency_status' => $emergencyStatus,
            'emergency_note' => $emergencyNote
        ]);
    }

    public function getDoctorsOnDuty()
    {
        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $doctors = User::where('userLevel', 'doctor')
            ->where('TodaysStatus', 'in duty')
            ->select('userID', 'first_name', 'last_name', 'specialization')
            ->get();

        return response()->json([
            'doctors' => $doctors
        ]);
    }

    public function getPatients()
    {
        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $patients = User::where('userLevel', 'patient')
            ->with('patient')
            ->get()
            ->map(function ($user) {
                return [
                    'userID' => $user->userID,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'hospitalNumber' => $user->patient ? $user->patient->hospitalNumber : 'N/A'
                ];
            });

        return response()->json([
            'patients' => $patients
        ]);
    }

    /**
     * Check if a patient has emergency status based on treatment data
     */
    private function checkEmergencyStatus($userID)
    {
        try {
            $patient = Patient::where('userID', $userID)->first();
            
            if (!$patient) {
                return [
                    'is_emergency' => false,
                    'priority' => 0,
                    'note' => 'Normal - Patient record not found'
                ];
            }

            $patientID = $patient->patientID;
            
            // Check for red color in outsolution (highest priority - critical)
            $hasRedColor = false;
            try {
                $hasRedColor = Outsolution::whereHas('treatment', function($query) use ($patientID) {
                        $query->where('patientID', $patientID);
                    })
                    ->where('Color', 'red')
                    ->whereDate('created_at', '>=', Carbon::now()->subDays(7))
                    ->exists();
            } catch (\Exception $e) {
                \Log::warning('Error checking red color for patient ' . $patientID . ': ' . $e->getMessage());
            }

            // Get recent treatments (last 7 days) with fluid balance data
            $recentTreatments = [];
            try {
                $recentTreatments = Treatment::where('patientID', $patientID)
                    ->whereDate('treatmentDate', '>=', Carbon::now()->subDays(7))
                    ->with(['insolution', 'outsolution'])
                    ->get();
            } catch (\Exception $e) {
                \Log::warning('Error fetching recent treatments for patient ' . $patientID . ': ' . $e->getMessage());
            }

            $significantImbalanceCount = 0;
            $criticalImbalanceCount = 0;
            
            foreach ($recentTreatments as $treatment) {
                try {
                    $volumeIn = 0;
                    $volumeOut = 0;
                    
                    if ($treatment->insolution) {
                        $volumeIn = $treatment->insolution->VolumeIn ?? 0;
                    }
                    
                    if ($treatment->outsolution) {
                        $volumeOut = $treatment->outsolution->VolumeOut ?? 0;
                    }
                    
                    // Calculate the difference
                    $difference = abs($volumeIn - $volumeOut);
                    
                    // Check for significant imbalance (difference > 10)
                    if ($difference > 10) {
                        $significantImbalanceCount++;
                        
                        // Check for critical imbalance (difference > 20)
                        if ($difference > 20) {
                            $criticalImbalanceCount++;
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Error processing treatment ' . ($treatment->treatmentID ?? 'unknown') . ': ' . $e->getMessage());
                    continue;
                }
            }

            // Calculate priority and build reasons
            $priority = 0;
            $reasons = [];

            // Red color is highest priority (critical) - 20 points
            if ($hasRedColor) {
                $priority += 20;
                $reasons[] = "Red urine color detected (critical)";
            }

            // Critical fluid imbalances (>20 difference) - 15 points each
            if ($criticalImbalanceCount > 0) {
                $priority += ($criticalImbalanceCount * 15);
                $reasons[] = "Critical fluid imbalance detected ({$criticalImbalanceCount} treatments with >20 difference)";
            }

            // Significant fluid imbalances (>10 difference) - 8 points each
            if ($significantImbalanceCount > 0) {
                $priority += ($significantImbalanceCount * 8);
                if ($criticalImbalanceCount === 0) {
                    $reasons[] = "Significant fluid imbalance detected ({$significantImbalanceCount} treatments with >10 difference)";
                }
            }

            // Determine if emergency (threshold of 10 points)
            $isEmergency = $priority >= 10;
            
            // Generate appropriate note
            if ($isEmergency) {
                if (empty($reasons)) {
                    $note = "Emergency: Abnormal treatment patterns detected";
                } else {
                    $note = "EMERGENCY: " . implode('; ', $reasons);
                }
            } else {
                $note = 'Normal - No significant issues detected';
                $priority = 0; // Reset priority for normal cases
            }

            // DEBUG: Log the detection results
            \Log::info("Emergency detection for patient $userID: ", [
                'is_emergency' => $isEmergency,
                'priority' => $priority,
                'note' => $note,
                'red_color' => $hasRedColor,
                'significant_imbalance_count' => $significantImbalanceCount,
                'critical_imbalance_count' => $criticalImbalanceCount,
                'total_treatments_analyzed' => count($recentTreatments)
            ]);

            return [
                'is_emergency' => $isEmergency,
                'priority' => $priority,
                'note' => $note
            ];
        } catch (\Exception $e) {
            \Log::error('Error in checkEmergencyStatus for user ' . $userID . ': ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return [
                'is_emergency' => false,
                'priority' => 0,
                'note' => 'Error analyzing patient data: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get number of treatments in the last 28 days for a patient
     */
    private function getTreatmentCountLast28Days($patientID)
    {
        try {
            $twentyEightDaysAgo = Carbon::now()->subDays(28)->format('Y-m-d');
            
            $treatmentCount = Treatment::where('patientID', $patientID)
                ->whereDate('treatmentDate', '>=', $twentyEightDaysAgo)
                ->count();

            return $treatmentCount;
        } catch (\Exception $e) {
            \Log::error('Error in getTreatmentCountLast28Days for patient ' . $patientID . ': ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Update emergency status for all patients in queue automatically
     */
    public function updateEmergencyStatuses()
    {
        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            $today = Carbon::today()->format('Y-m-d');
            
            $queues = Queue::whereDate('appointment_date', $today)
                ->where('status', 'waiting')
                ->get();
            
            $updatedCount = 0;
            foreach ($queues as $queue) {
                $emergencyData = $this->checkEmergencyStatus($queue->userID);
                
                // Only update if changed
                if ($queue->emergency_status != $emergencyData['is_emergency'] || 
                    $queue->emergency_priority != $emergencyData['priority'] ||
                    $queue->emergency_note != $emergencyData['note']) {
                    
                    $queue->emergency_status = $emergencyData['is_emergency'];
                    $queue->emergency_priority = $emergencyData['priority'];
                    $queue->emergency_note = $emergencyData['note'];
                    $queue->save();
                    $updatedCount++;
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Emergency statuses updated successfully',
                'updated_count' => $updatedCount
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Error updating emergency statuses: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to update emergency statuses: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getDoctorQueues()
    {
        $user = auth()->user();
        
        if (!$user || $user->userLevel !== 'doctor') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $today = Carbon::today()->format('Y-m-d');
        
        $queues = Queue::with(['user', 'patient.user'])
            ->whereDate('appointment_date', $today)
            ->where('doctor_id', $user->userID)
            ->whereNotNull('queue_number')
            ->orderBy('emergency_status', 'desc')
            ->orderBy('emergency_priority', 'desc')
            ->orderBy('queue_number', 'asc')
            ->get()
            ->map(function ($queue) {
                $patientName = 'Unknown';
                $hospitalNumber = 'N/A';
                
                if ($queue->patient && $queue->patient->user) {
                    $patientName = $queue->patient->user->first_name . ' ' . $queue->patient->user->last_name;
                    $hospitalNumber = $queue->patient->hospitalNumber;
                } elseif ($queue->user) {
                    $patientName = $queue->user->first_name . ' ' . $queue->user->last_name;
                    $patient = Patient::where('userID', $queue->userID)->first();
                    if ($patient) {
                        $hospitalNumber = $patient->hospitalNumber;
                    }
                }
                
                return [
                    'queue_id' => $queue->queue_id,
                    'queue_number' => $queue->queue_number,
                    'patient_name' => $patientName,
                    'hospital_number' => $hospitalNumber,
                    'status' => $queue->status,
                    'emergency_status' => (bool)$queue->emergency_status,
                    'emergency_priority' => $queue->emergency_priority,
                    'emergency_note' => $queue->emergency_note,
                    'start_time' => $queue->start_time,
                    'appointment_date' => $queue->appointment_date,
                    'doctor_id' => $queue->doctor_id,
                    'checkup_status' => $queue->checkup_status,
                    'userID' => $queue->userID
                ];
            });

        return response()->json([
            'queues' => $queues,
            'current_date' => $today
        ]);
    }

    /**
     * Get queue statistics for dashboard
     */
    public function getQueueStatistics()
    {
        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse', 'doctor'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $today = Carbon::today()->format('Y-m-d');
        
        $statistics = [
            'total_patients' => Queue::whereDate('appointment_date', $today)->count(),
            'waiting_patients' => Queue::whereDate('appointment_date', $today)
                ->where('status', 'waiting')
                ->count(),
            'in_progress_patients' => Queue::whereDate('appointment_date', $today)
                ->where('status', 'in-progress')
                ->count(),
            'completed_patients' => Queue::whereDate('appointment_date', $today)
                ->where('status', 'completed')
                ->count(),
            'emergency_patients' => Queue::whereDate('appointment_date', $today)
                ->where('emergency_status', true)
                ->count(),
            'available_doctors' => User::where('userLevel', 'doctor')
                ->where('TodaysStatus', 'in duty')
                ->whereNotExists(function ($query) use ($today) {
                    $query->select(DB::raw(1))
                        ->from('queue')
                        ->whereRaw('queue.doctor_id = users.userID')
                        ->where('queue.status', 'in-progress')
                        ->whereDate('queue.appointment_date', $today);
                })
                ->count()
        ];

        return response()->json([
            'success' => true,
            'statistics' => $statistics,
            'current_date' => $today
        ]);
    }

    /**
     * Move emergency patient to front of queue
     */
    public function prioritizeEmergencyPatient(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'queue_id' => 'required|integer|exists:queue,queue_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            $queue = Queue::find($request->queue_id);
            
            if (!$queue) {
                return response()->json(['error' => 'Queue not found'], 404);
            }

            if (!$queue->emergency_status) {
                return response()->json([
                    'error' => 'Patient is not marked as emergency'
                ], 400);
            }

            if ($queue->status !== 'waiting') {
                return response()->json([
                    'error' => 'Only waiting patients can be prioritized'
                ], 400);
            }

            $today = Carbon::today()->format('Y-m-d');
            
            // Get the current minimum queue number for waiting patients
            $minQueueNumber = Queue::whereDate('appointment_date', $today)
                ->where('status', 'waiting')
                ->min('queue_number');
            
            if ($minQueueNumber === null) {
                return response()->json([
                    'error' => 'No waiting patients found'
                ], 400);
            }

            // If already at front, no need to move
            if ($queue->queue_number === $minQueueNumber) {
                return response()->json([
                    'success' => true,
                    'message' => 'Patient is already at the front of the queue',
                    'queue' => $queue
                ]);
            }

            // Move patient to front
            $queue->queue_number = $minQueueNumber - 1; // Set before the current first
            $queue->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Emergency patient moved to front of queue',
                'queue' => $queue
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error prioritizing emergency patient: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to prioritize patient: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send emergency patient directly to emergency department (remove from queue and update patient status)
     */
    public function sendToEmergency(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'queue_id' => 'required|integer|exists:queue,queue_id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        
        if (!$user || !in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            DB::beginTransaction();

            $queue = Queue::find($request->queue_id);
            
            if (!$queue) {
                return response()->json(['error' => 'Queue not found'], 404);
            }

            if (!$queue->emergency_status) {
                return response()->json([
                    'error' => 'Patient is not marked as emergency'
                ], 400);
            }

            if ($queue->status !== 'waiting') {
                return response()->json([
                    'error' => 'Only waiting patients can be sent to emergency'
                ], 400);
            }

            // Get patient info
            $patient = Patient::where('userID', $queue->userID)->first();
            $patientName = 'Unknown Patient';
            
            if ($queue->user) {
                $patientName = $queue->user->first_name . ' ' . $queue->user->last_name;
            } elseif ($patient) {
                $patientUser = User::find($patient->userID);
                if ($patientUser) {
                    $patientName = $patientUser->first_name . ' ' . $patientUser->last_name;
                }
            }

            // Update patient's situationStatus to 'InEmergency'
            if ($patient) {
                $patient->situationStatus = 'InEmergency';
                $patient->save();
            }

            // Remove from queue by setting status to completed with emergency note
            $queue->status = 'completed';
            $queue->checkup_status = 'Completed';
            $queue->emergency_note = $queue->emergency_note . " (Sent directly to emergency department)";
            $queue->save();

            DB::commit();

            // Log the action
            $patientID = $patient ? $patient->patientID : 'N/A';
            \Log::info("Patient sent to emergency department: {$patientName} (Queue ID: {$queue->queue_id}, Patient ID: {$patientID})");

            return response()->json([
                'success' => true,
                'message' => 'Patient sent directly to emergency department and status updated',
                'patient_name' => $patientName,
                'emergency_priority' => $queue->emergency_priority,
                'emergency_note' => $queue->emergency_note,
                'patient_status_updated' => $patient ? true : false
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error sending patient to emergency: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to send patient to emergency: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get enhanced patient data including treatment count and emergency status
     */
    public function getEnhancedPatientData($userID)
    {
        try {
            // Validate userID
            if (!is_numeric($userID)) {
                return response()->json([
                    'treatment_count_28_days' => 0,
                    'is_emergency' => false,
                    'emergency_priority' => 0,
                    'emergency_note' => 'Invalid user ID',
                    'situation_status' => 'Unknown'
                ], 200);
            }

            $patient = Patient::where('userID', $userID)->first();
            
            if (!$patient) {
                return response()->json([
                    'treatment_count_28_days' => 0,
                    'is_emergency' => false,
                    'emergency_priority' => 0,
                    'emergency_note' => 'Patient record not found',
                    'situation_status' => 'Unknown'
                ], 200);
            }

            $emergencyData = $this->checkEmergencyStatus($userID);
            $treatmentCount = $this->getTreatmentCountLast28Days($patient->patientID);

            return response()->json([
                'treatment_count_28_days' => $treatmentCount,
                'is_emergency' => $emergencyData['is_emergency'],
                'emergency_priority' => $emergencyData['priority'],
                'emergency_note' => $emergencyData['note'],
                'situation_status' => $patient->situationStatus ?? 'Normal'
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error in getEnhancedPatientData for user ' . $userID . ': ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'treatment_count_28_days' => 0,
                'is_emergency' => false,
                'emergency_priority' => 0,
                'emergency_note' => 'System error fetching patient data',
                'situation_status' => 'Error'
            ], 200);
        }
    }
}