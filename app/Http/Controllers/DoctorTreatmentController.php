<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DoctorTreatmentController extends Controller
{
    public function getPatientTreatments(Request $request)
    {
        $search = $request->query('search', '');
        $dateFrom = $request->query('date_from', '');
        $dateTo = $request->query('date_to', '');
        $status = $request->query('status', 'all');

        // Get the authenticated user
        $user = Auth::user();

        // Verify the user is a doctor
        if ($user->userLevel !== 'doctor') {
            return response()->json([
                'success' => false,
                'message' => 'Only doctors can access patient treatments'
            ], 403);
        }

        // First get all patients (even those without treatments)
        $patients = DB::table('patients as p')
            ->select(
                'p.patientID',
                'p.hospitalNumber',
                'p.legalRepresentative',
                'p.situationStatus',
                DB::raw("CONCAT(u.first_name, ' ', COALESCE(u.middle_name, ''), ' ', u.last_name) AS name"),
                'u.first_name',
                'u.middle_name',
                'u.last_name',
                'u.date_of_birth',
                'u.gender',
                'u.email',
                // 'u.phone',
                DB::raw("COALESCE(er.status, 'not_recommended') as emergency_status")
            )
            ->join('users as u', 'p.userID', '=', 'u.userID')
            ->leftJoin('emergency_recommendations as er', function($join) use ($user) {
                $join->on('p.patientID', '=', 'er.patient_id')
                     ->where('er.doctor_id', '=', $user->userID)
                     ->where('er.status', '=', 'sent');
            })
            ->where('u.userLevel', 'patient')
            ->orderBy('name')
            ->get();

        if (!empty($search) && strlen($search) >= 2) {
            $patients = $patients->filter(function($patient) use ($search) {
                return stripos($patient->name, $search) !== false || 
                       stripos($patient->hospitalNumber, $search) !== false ||
                       stripos($patient->legalRepresentative, $search) !== false;
            });
        }

        $patientIds = $patients->pluck('patientID')->toArray();

        // Now get ALL treatments for these patients with complete details
        $treatmentsQuery = DB::table('treatment as t')
            ->select(
                't.patientID',
                't.Treatment_ID',
                't.treatmentDate',
                't.TreatmentStatus',
                'i.InStarted',
                'i.InFinished',
                'i.VolumeIn',
                'i.Dialysate',
                'i.Dwell',
                'o.DrainStarted',
                'o.DrainFinished',
                'o.VolumeOut',
                'o.Color',
                'o.Notes'
            )
            ->leftJoin('insolution as i', 't.IN_ID', '=', 'i.IN_ID')
            ->leftJoin('outsolution as o', 't.OUT_ID', '=', 'o.OUT_ID')
            ->whereIn('t.patientID', $patientIds);

        // Apply date filters only if provided
        if (!empty($dateFrom)) {
            $treatmentsQuery->whereDate('t.treatmentDate', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $treatmentsQuery->whereDate('t.treatmentDate', '<=', $dateTo);
        }

        $treatments = $treatmentsQuery->get()
            ->groupBy('patientID');

        // Combine patients with their treatments
        $patientsWithTreatments = [];
        foreach ($patients as $patient) {
            $patientTreatments = isset($treatments[$patient->patientID]) ? 
                $treatments[$patient->patientID]->map(function($treatment) {
                    return [
                        'Treatment_ID' => $treatment->Treatment_ID,
                        'treatmentDate' => $treatment->treatmentDate,
                        'TreatmentStatus' => $treatment->TreatmentStatus,
                        'InStarted' => $treatment->InStarted,
                        'InFinished' => $treatment->InFinished,
                        'VolumeIn' => $treatment->VolumeIn,
                        'Dialysate' => $treatment->Dialysate,
                        'Dwell' => $treatment->Dwell,
                        'DrainStarted' => $treatment->DrainStarted,
                        'DrainFinished' => $treatment->DrainFinished,
                        'VolumeOut' => $treatment->VolumeOut,
                        'Color' => $treatment->Color,
                        'Notes' => $treatment->Notes
                    ];
                })->toArray() : [];

            $patientsWithTreatments[] = [
                'patientID' => $patient->patientID,
                'name' => trim($patient->name),
                'middle_name' => $patient->middle_name,
                'hospitalNumber' => $patient->hospitalNumber,
                'date_of_birth' => $patient->date_of_birth,
                'gender' => $patient->gender,
                'email' => $patient->email,
                // 'phone' => $patient->phone,
                'legalRepresentative' => $patient->legalRepresentative,
                'situationStatus' => $patient->situationStatus,
                'emergency_status' => $patient->emergency_status,
                'treatments' => $patientTreatments
            ];
        }

        // Calculate summary statistics
        $summary = [
            'total_patients' => count($patientsWithTreatments),
            'total_treatments' => array_sum(array_map(function($patient) {
                return count($patient['treatments']);
            }, $patientsWithTreatments)),
            'non_compliant_patients' => 0,
            'fluid_retention_alerts' => 0,
            'abnormal_color_alerts' => 0,
            'severe_retention_cases' => 0,
            'at_home_patients' => 0,
            'in_emergency_patients' => 0,
            'waiting_response_patients' => 0
        ];

        foreach ($patientsWithTreatments as &$patient) {
            $treatmentDates = [];
            $severeRetentionDays = 0;
            
            // Count situation status
            if ($patient['situationStatus'] === 'AtHome') {
                $summary['at_home_patients']++;
            } elseif ($patient['situationStatus'] === 'InEmergency') {
                $summary['in_emergency_patients']++;
            } elseif ($patient['situationStatus'] === 'WaitToResponse') {
                $summary['waiting_response_patients']++;
            }
            
            foreach ($patient['treatments'] as $treatment) {
                $date = Carbon::parse($treatment['treatmentDate'])->toDateString();
                if (!isset($treatmentDates[$date])) {
                    $treatmentDates[$date] = [
                        'count' => 0,
                        'completed' => 0,
                        'retentionCount' => 0
                    ];
                }
                $treatmentDates[$date]['count']++;
                
                // Check for fluid retention
                $volumeIn = $treatment['VolumeIn'] ?? 0;
                $volumeOut = $treatment['VolumeOut'] ?? 0;
                if (($volumeOut - $volumeIn) < 0) {
                    $treatmentDates[$date]['retentionCount']++;
                }
                
                if (strtolower($treatment['TreatmentStatus']) === 'finished') {
                    $treatmentDates[$date]['completed']++;
                }
            }

            // Calculate compliance
            $incompleteDays = 0;
            foreach ($treatmentDates as $date => $data) {
                if ($data['completed'] < 3 && Carbon::parse($date)->isBefore(now()->startOfDay())) {
                    $incompleteDays++;
                }
                
                // Check for severe retention (all treatments in a day show retention)
                if ($data['retentionCount'] === $data['count'] && $data['count'] > 0) {
                    $severeRetentionDays++;
                }
            }

            $patient['incompleteDays'] = $incompleteDays;
            $patient['severeRetentionDays'] = $severeRetentionDays;
            
            // Calculate fluid retention alerts
            $totalVolumeIn = array_sum(array_column($patient['treatments'], 'VolumeIn'));
            $totalVolumeOut = array_sum(array_column($patient['treatments'], 'VolumeOut'));
            $treatmentCount = count($patient['treatments']);
            $avgVolumeIn = $treatmentCount > 0 ? $totalVolumeIn / $treatmentCount : 0;
            $avgVolumeOut = $treatmentCount > 0 ? $totalVolumeOut / $treatmentCount : 0;
            
            if (($avgVolumeIn - $avgVolumeOut) > 200) {
                $summary['fluid_retention_alerts']++;
            }

            // Calculate abnormal color alerts
            $abnormalColors = array_filter($patient['treatments'], function($treatment) {
                $color = strtolower($treatment['Color'] ?? '');
                return !empty($color) && !in_array($color, ['clear', 'yellow', 'amber']);
            });
                
            if (count($abnormalColors) > 0) {
                $summary['abnormal_color_alerts']++;
            }

            // Count severe retention cases
            if ($severeRetentionDays > 0) {
                $summary['severe_retention_cases']++;
            }

            // Count as non-compliant if any incomplete days
            if ($incompleteDays > 0) {
                $summary['non_compliant_patients']++;
            }
        }

        // Filter patients based on status
        if ($status === 'non-compliant') {
            $patientsWithTreatments = array_filter($patientsWithTreatments, function($patient) {
                return $patient['incompleteDays'] > 0;
            });
        } elseif ($status === 'abnormal') {
            $patientsWithTreatments = array_filter($patientsWithTreatments, function($patient) use ($summary) {
                $totalVolumeIn = array_sum(array_column($patient['treatments'], 'VolumeIn'));
                $totalVolumeOut = array_sum(array_column($patient['treatments'], 'VolumeOut'));
                $treatmentCount = count($patient['treatments']);
                $avgVolumeIn = $treatmentCount > 0 ? $totalVolumeIn / $treatmentCount : 0;
                $avgVolumeOut = $treatmentCount > 0 ? $totalVolumeOut / $treatmentCount : 0;
                $fluidDifference = $avgVolumeIn - $avgVolumeOut;
                
                $hasAbnormalColor = count(array_filter($patient['treatments'], function($treatment) {
                    $color = strtolower($treatment['Color'] ?? '');
                    return !empty($color) && !in_array($color, ['clear', 'yellow', 'amber']);
                })) > 0;
                
                return $patient['incompleteDays'] > 0 || 
                       $fluidDifference > 200 ||
                       $hasAbnormalColor ||
                       $patient['severeRetentionDays'] > 0;
            });
        } elseif ($status === 'at_home') {
            $patientsWithTreatments = array_filter($patientsWithTreatments, function($patient) {
                return $patient['situationStatus'] === 'AtHome';
            });
        } elseif ($status === 'in_emergency') {
            $patientsWithTreatments = array_filter($patientsWithTreatments, function($patient) {
                return $patient['situationStatus'] === 'InEmergency';
            });
        } elseif ($status === 'waiting_response') {
            $patientsWithTreatments = array_filter($patientsWithTreatments, function($patient) {
                return $patient['situationStatus'] === 'WaitToResponse';
            });
        }

        return response()->json([
            'success' => true,
            'patients' => array_values($patientsWithTreatments),
            'summary' => $summary
        ]);
    }

    public function recommendEmergency(Request $request)
    {
        $user = Auth::user();

        // Verify the user is a doctor
        if ($user->userLevel !== 'doctor') {
            return response()->json([
                'success' => false,
                'message' => 'Only doctors can recommend emergency visits'
            ], 403);
        }

        $validated = $request->validate([
            'patient_id' => 'required|integer|exists:patients,patientID'
        ]);

        try {
            // Update patient's situation status to WaitToResponse
            DB::table('patients')
                ->where('patientID', $validated['patient_id'])
                ->update(['situationStatus' => 'WaitToResponse']);

            // Check if recommendation already exists
            $existingRecommendation = DB::table('emergency_recommendations')
                ->where('patient_id', $validated['patient_id'])
                ->where('doctor_id', $user->userID)
                ->first();

            if ($existingRecommendation) {
                // Update existing recommendation
                DB::table('emergency_recommendations')
                    ->where('recommendation_id', $existingRecommendation->recommendation_id)
                    ->update([
                        'status' => 'sent',
                        'recommended_at' => now()
                    ]);
            } else {
                // Create new recommendation
                DB::table('emergency_recommendations')->insert([
                    'patient_id' => $validated['patient_id'],
                    'doctor_id' => $user->userID,
                    'status' => 'sent',
                    'recommended_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Emergency hospital visit recommended successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to recommend emergency visit: ' . $e->getMessage()
            ], 500);
        }
    }

    public function recommendEmergencyToAll(Request $request)
    {
        $user = Auth::user();

        // Verify the user is a doctor
        if ($user->userLevel !== 'doctor') {
            return response()->json([
                'success' => false,
                'message' => 'Only doctors can recommend emergency visits'
            ], 403);
        }

        $validated = $request->validate([
            'patient_ids' => 'required|array',
            'patient_ids.*' => 'integer|exists:patients,patientID'
        ]);

        try {
            // Update all patients' situation status to WaitToResponse
            DB::table('patients')
                ->whereIn('patientID', $validated['patient_ids'])
                ->update(['situationStatus' => 'WaitToResponse']);

            foreach ($validated['patient_ids'] as $patientId) {
                // Check if recommendation already exists
                $existingRecommendation = DB::table('emergency_recommendations')
                    ->where('patient_id', $patientId)
                    ->where('doctor_id', $user->userID)
                    ->first();

                if ($existingRecommendation) {
                    // Update existing recommendation
                    DB::table('emergency_recommendations')
                        ->where('recommendation_id', $existingRecommendation->recommendation_id)
                        ->update([
                            'status' => 'sent',
                            'recommended_at' => now()
                        ]);
                } else {
                    // Create new recommendation
                    DB::table('emergency_recommendations')->insert([
                        'patient_id' => $patientId,
                        'doctor_id' => $user->userID,
                        'status' => 'sent',
                        'recommended_at' => now()
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Emergency hospital visits recommended to all selected patients'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to recommend emergency visits: ' . $e->getMessage()
            ], 500);
        }
    }
}