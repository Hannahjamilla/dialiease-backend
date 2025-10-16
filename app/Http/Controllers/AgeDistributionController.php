<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Patient;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AgeDistributionController extends Controller
{
    public function getAgeDistribution(Request $request)
    {
        $type = $request->get('type', 'patients'); // 'patients' or 'employees'
        
        // Validate inputs
        if (!in_array($type, ['patients', 'employees'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid type. Must be "patients" or "employees".'
            ], 400);
        }
        
        try {
            $distributionData = $this->calculateAgeDistribution($type);
            $summaryStats = $this->calculateSummaryStats($distributionData);
            
            return response()->json([
                'success' => true,
                'data' => $distributionData,
                'summary' => $summaryStats,
                'type' => $type
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Age Distribution Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch age distribution data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    private function calculateAgeDistribution($type)
    {
        // Initialize arrays for age distribution (by single years)
        $ageDistribution = [];
        $allAges = [];
        
        // Initialize age buckets from 0 to 100+
        for ($i = 0; $i <= 100; $i++) {
            $ageDistribution[$i] = 0;
        }
        $ageDistribution['100+'] = 0;
        
        if ($type === 'employees') {
            // Get ALL employees (non-patient users)
            $users = User::whereNotNull('date_of_birth')
                        ->where('userLevel', '!=', 'patient')
                        ->get(['userID', 'date_of_birth']);
            
            foreach ($users as $user) {
                try {
                    if (empty($user->date_of_birth)) continue;
                    
                    $dateOfBirth = Carbon::parse($user->date_of_birth);
                    
                    // Skip if birth date is in the future
                    if ($dateOfBirth->greaterThan(Carbon::now())) {
                        continue;
                    }
                    
                    // Calculate current age
                    $age = $dateOfBirth->diffInYears(Carbon::now());
                    $allAges[] = $age;
                    
                    // Categorize into age distribution
                    if ($age >= 100) {
                        $ageDistribution['100+']++;
                    } elseif ($age <= 100) {
                        $ageDistribution[$age]++;
                    }
                    
                } catch (\Exception $e) {
                    continue;
                }
            }
            
        } else {
            // Get ALL patients (users with userLevel = 'patient')
            $users = User::whereNotNull('date_of_birth')
                      ->where('userLevel', 'patient')
                      ->get(['userID', 'date_of_birth']);
            
            foreach ($users as $user) {
                try {
                    if (empty($user->date_of_birth)) continue;
                    
                    $dateOfBirth = Carbon::parse($user->date_of_birth);
                    
                    // Skip if birth date is in the future
                    if ($dateOfBirth->greaterThan(Carbon::now())) {
                        continue;
                    }
                    
                    // Calculate current age
                    $age = $dateOfBirth->diffInYears(Carbon::now());
                    $allAges[] = $age;
                    
                    // Categorize into age distribution
                    if ($age >= 100) {
                        $ageDistribution['100+']++;
                    } elseif ($age <= 100) {
                        $ageDistribution[$age]++;
                    }
                    
                } catch (\Exception $e) {
                    continue;
                }
            }
            
            // If no patients found in users table, try from patients table
            if (count($allAges) === 0) {
                $patients = Patient::with(['user' => function($query) {
                                $query->whereNotNull('date_of_birth');
                            }])
                            ->whereHas('user', function($query) {
                                $query->whereNotNull('date_of_birth');
                            })
                            ->get();
                
                foreach ($patients as $patient) {
                    try {
                        if (!$patient->user || empty($patient->user->date_of_birth)) continue;
                        
                        $dateOfBirth = Carbon::parse($patient->user->date_of_birth);
                        
                        if ($dateOfBirth->greaterThan(Carbon::now())) {
                            continue;
                        }
                        
                        $age = $dateOfBirth->diffInYears(Carbon::now());
                        $allAges[] = $age;
                        
                        if ($age >= 100) {
                            $ageDistribution['100+']++;
                        } elseif ($age <= 100) {
                            $ageDistribution[$age]++;
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }
        
        // Prepare data for line chart (ages 0-100)
        $ages = range(0, 100);
        $counts = [];
        foreach ($ages as $age) {
            $counts[] = $ageDistribution[$age];
        }
        
        // Add 100+ category if needed
        if ($ageDistribution['100+'] > 0) {
            $ages[] = '100+';
            $counts[] = $ageDistribution['100+'];
        }
        
        return [
            'ages' => $ages,
            'counts' => $counts,
            'all_ages' => $allAges,
            'total_records' => count($allAges),
            'average_age' => count($allAges) > 0 ? round(array_sum($allAges) / count($allAges), 1) : 0,
            'min_age' => count($allAges) > 0 ? min($allAges) : 0,
            'max_age' => count($allAges) > 0 ? max($allAges) : 0
        ];
    }
    
    private function calculateSummaryStats($distributionData)
    {
        $total = $distributionData['total_records'];
        
        return [
            'total' => $total,
            'average_age' => $distributionData['average_age'],
            'min_age' => $distributionData['min_age'],
            'max_age' => $distributionData['max_age']
        ];
    }
}