<?php

namespace App\Http\Controllers;

use App\Models\Treatment;
use App\Models\Insolution;
use App\Models\Outsolution;
use App\Models\User;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CUpSidePatientTreatController extends Controller
{
    /**
     * Get ALL patient treatment data with related in/out solution data
     */
    public function getPatientTreatments($patientId)
    {
        try {
            Log::info('Fetching ALL treatments for patient ID: ' . $patientId);
            
            // First try to find patient by patientID
            $patient = Patient::where('patientID', $patientId)->first();
            
            if (!$patient) {
                // If not found by patientID, try by userID
                $patient = Patient::where('userID', $patientId)->first();
                
                if (!$patient) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Patient not found with ID: ' . $patientId
                    ], 404);
                }
            }

            // Get user details
            $user = User::where('userID', $patient->userID)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User details not found for patient'
                ], 404);
            }

            // Get ALL treatments for this patient
            $treatments = Treatment::where('patientID', $patient->patientID)
                ->orderBy('treatmentDate', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('Found ' . $treatments->count() . ' treatments for patient ID: ' . $patient->patientID);

            $enrichedTreatments = [];
            
            foreach ($treatments as $treatment) {
                $insolution = null;
                $outsolution = null;
                
                // Get insolution data if IN_ID exists
                if (!empty($treatment->IN_ID)) {
                    $insolution = Insolution::where('IN_ID', $treatment->IN_ID)->first();
                }
                
                // Get outsolution data if OUT_ID exists
                if (!empty($treatment->OUT_ID)) {
                    $outsolution = Outsolution::where('OUT_ID', $treatment->OUT_ID)->first();
                }
                
                // Calculate volumes and balance
                $volumeIn = $insolution->VolumeIn ?? 0;
                $volumeOut = $outsolution->VolumeOut ?? 0;
                $balance = !empty($treatment->Balances) ? $treatment->Balances : ($volumeOut - $volumeIn);
                
                $enrichedTreatments[] = [
                    'Treatment_ID' => $treatment->Treatment_ID,
                    'patientID' => $treatment->patientID,
                    'TreatmentStatus' => $treatment->TreatmentStatus,
                    'Balances' => $balance,
                    'treatmentDate' => $treatment->treatmentDate,
                    'bagSerialNumber' => $treatment->bagSerialNumber,
                    'dry_night' => $treatment->dry_night,
                    'VolumeIn' => $volumeIn,
                    'VolumeOut' => $volumeOut,
                    'Color' => $outsolution->Color ?? null,
                    'Notes' => $outsolution->Notes ?? null,
                    'Dialysate' => $insolution->Dialysate ?? null,
                    'Dwell' => $insolution->Dwell ?? null,
                    'InStarted' => $insolution->InStarted ?? null,
                    'InFinished' => $insolution->InFinished ?? null,
                    'DrainStarted' => $outsolution->DrainStarted ?? null,
                    'DrainFinished' => $outsolution->DrainFinished ?? null,
                    'ExitSiteImage' => $outsolution->ExitSiteImage ?? null,
                    'solutionImage' => $treatment->solutionImage ?? null,
                    'created_at' => $treatment->created_at,
                    'updated_at' => $treatment->updated_at
                ];
            }

            Log::info('Successfully processed ' . count($enrichedTreatments) . ' enriched treatments');

            return response()->json([
                'success' => true,
                'patient' => [
                    'patientID' => $patient->patientID,
                    'userID' => $user->userID,
                    'name' => trim($user->first_name . ' ' . ($user->middle_name ? $user->middle_name . ' ' : '') . $user->last_name),
                    'first_name' => $user->first_name,
                    'middle_name' => $user->middle_name,
                    'last_name' => $user->last_name,
                    'date_of_birth' => $user->date_of_birth,
                    'gender' => $user->gender,
                    'email' => $user->email,
                    'hospitalNumber' => $patient->hospitalNumber,
                    'modality' => $patient->modality ?? 'PD',
                    'legalRepresentative' => $patient->legalRepresentative,
                    'situationStatus' => $patient->situationStatus
                ],
                'treatments' => $enrichedTreatments,
                'alerts' => $this->generateAlerts($enrichedTreatments),
                'summary' => [
                    'total_treatments' => count($enrichedTreatments),
                    'treatments_with_insolution' => count(array_filter($enrichedTreatments, function($t) { return $t['VolumeIn'] > 0; })),
                    'treatments_with_outsolution' => count(array_filter($enrichedTreatments, function($t) { return $t['VolumeOut'] > 0; })),
                    'completed_treatments' => count(array_filter($enrichedTreatments, function($t) { 
                        return isset($t['TreatmentStatus']) && strtolower($t['TreatmentStatus']) === 'completed'; 
                    })),
                    'date_range' => $this->getDateRange($enrichedTreatments)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching patient treatments: ' . $e->getMessage());
            Log::error('Error in file: ' . $e->getFile() . ' on line: ' . $e->getLine());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch patient treatment data',
                'error' => env('APP_DEBUG') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Generate clinical alerts based on treatment data
     */
    private function generateAlerts($treatments)
    {
        $alerts = [];
        
        if (empty($treatments)) {
            return $alerts;
        }

        // Group treatments by date
        $treatmentsByDate = [];
        foreach ($treatments as $treatment) {
            $date = Carbon::parse($treatment['treatmentDate'])->format('Y-m-d');
            if (!isset($treatmentsByDate[$date])) {
                $treatmentsByDate[$date] = [];
            }
            $treatmentsByDate[$date][] = $treatment;
        }

        // Check for incomplete treatment days (less than 3 treatments per day)
        foreach ($treatmentsByDate as $date => $dailyTreatments) {
            if (count($dailyTreatments) < 3 && Carbon::parse($date)->isBefore(now()->startOfDay())) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Incomplete Treatments',
                    'message' => 'Only ' . count($dailyTreatments) . ' of 3 treatments completed on ' . Carbon::parse($date)->format('M j, Y'),
                    'date' => $date
                ];
            }
        }

        // Check for fluid retention patterns
        $retentionCount = 0;
        $totalTreatments = count($treatments);
        
        foreach ($treatments as $treatment) {
            if ($treatment['Balances'] > 0) { // Positive balance means fluid retention
                $retentionCount++;
            }
        }
        
        if ($retentionCount > 0) {
            $retentionPercentage = ($retentionCount / $totalTreatments) * 100;
            if ($retentionPercentage >= 50) {
                $alerts[] = [
                    'type' => 'danger',
                    'title' => 'Fluid Retention Pattern',
                    'message' => $retentionCount . ' of ' . $totalTreatments . ' treatments show fluid retention (' . round($retentionPercentage) . '%)',
                    'priority' => 'high'
                ];
            }
        }

        // Check for abnormal drain colors
        $abnormalColors = array_filter($treatments, function($treatment) {
            $color = strtolower($treatment['Color'] ?? '');
            return !empty($color) && !in_array($color, ['clear', 'yellow', 'amber', 'straw', 'pale yellow']);
        });
        
        if (count($abnormalColors) > 0) {
            $alerts[] = [
                'type' => 'danger',
                'title' => 'Abnormal Drain Color',
                'message' => count($abnormalColors) . ' treatments with unusual drain color detected',
                'priority' => 'high'
            ];
        }

        return $alerts;
    }

    /**
     * Get date range from treatments
     */
    private function getDateRange($treatments)
    {
        if (empty($treatments)) {
            return [
                'start_date' => null,
                'end_date' => null,
                'days_count' => 0
            ];
        }

        $dates = array_map(function($treatment) {
            return Carbon::parse($treatment['treatmentDate']);
        }, $treatments);

        $startDate = min($dates);
        $endDate = max($dates);
        $daysCount = $treatments ? count(array_unique(array_map(function($t) {
            return Carbon::parse($t['treatmentDate'])->format('Y-m-d');
        }, $treatments))) : 0;

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'days_count' => $daysCount
        ];
    }

    /**
     * Get additional patient statistics
     */
    public function getPatientStatistics($patientId)
    {
        try {
            // Find patient first
            $patient = Patient::where('patientID', $patientId)->first();
            if (!$patient) {
                $patient = Patient::where('userID', $patientId)->first();
            }

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            // Get basic treatment count
            $treatmentCount = Treatment::where('patientID', $patient->patientID)->count();
            
            // Get date range of treatments
            $dateRange = Treatment::where('patientID', $patient->patientID)
                ->selectRaw('MIN(treatmentDate) as start_date, MAX(treatmentDate) as end_date')
                ->first();
            
            // Get status distribution
            $statusDistribution = Treatment::where('patientID', $patient->patientID)
                ->select('TreatmentStatus', DB::raw('COUNT(*) as count'))
                ->groupBy('TreatmentStatus')
                ->get();
            
            return response()->json([
                'success' => true,
                'statistics' => [
                    'total_treatments' => $treatmentCount,
                    'date_range' => $dateRange,
                    'status_distribution' => $statusDistribution
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching patient statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch patient statistics'
            ], 500);
        }
    }

    /**
     * Get patient treatment summary for dashboard
     */
    public function getPatientTreatmentSummary($patientId)
    {
        try {
            // Find patient first
            $patient = Patient::where('patientID', $patientId)->first();
            if (!$patient) {
                $patient = Patient::where('userID', $patientId)->first();
            }

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            $treatments = Treatment::where('patientID', $patient->patientID)
                ->orderBy('treatmentDate', 'desc')
                ->get();

            $summary = [
                'total_treatments' => $treatments->count(),
                'completed_treatments' => $treatments->where('TreatmentStatus', 'completed')->count(),
                'last_treatment_date' => $treatments->first() ? $treatments->first()->treatmentDate : null,
                'treatment_days' => $treatments->groupBy('treatmentDate')->count()
            ];

            return response()->json([
                'success' => true,
                'summary' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching patient treatment summary: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch treatment summary'
            ], 500);
        }
    }
}