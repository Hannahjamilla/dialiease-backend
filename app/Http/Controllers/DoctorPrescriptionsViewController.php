<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DoctorPrescriptionsViewController extends Controller
{
    public function getPatientPrescriptions(Request $request)
    {
        $search = $request->query('search', '');
        $dateFrom = $request->query('date_from', '');
        $dateTo = $request->query('date_to', '');
        $system = $request->query('system', 'all');

        try {
            // Get all patients (even those without prescriptions)
            $patientsQuery = DB::table('patients as p')
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
                    'u.email'
                )
                ->join('users as u', 'p.userID', '=', 'u.userID')
                ->where('u.userLevel', 'patient')
                ->orderBy('name');

            // Apply search filter
            if (!empty($search) && strlen($search) >= 2) {
                $patientsQuery->where(function($query) use ($search) {
                    $query->where(DB::raw("CONCAT(u.first_name, ' ', COALESCE(u.middle_name, ''), ' ', u.last_name)"), 'LIKE', "%{$search}%")
                          ->orWhere('p.hospitalNumber', 'LIKE', "%{$search}%")
                          ->orWhere('p.legalRepresentative', 'LIKE', "%{$search}%");
                });
            }

            $patients = $patientsQuery->get();

            $patientIds = $patients->pluck('patientID')->toArray();

            // Get prescriptions for these patients
            $prescriptionsQuery = DB::table('prescriptions as pr')
                ->select(
                    'pr.id',
                    'pr.patientID',
                    'pr.userID',
                    'pr.additional_instructions',
                    'pr.pd_system',
                    'pr.pd_modality',
                    'pr.pd_total_exchanges',
                    'pr.pd_fill_volume',
                    'pr.pd_dwell_time',
                    'pr.pd_exchanges',
                    'pr.pd_bag_percentages',
                    'pr.pd_bag_counts',
                    'pr.created_at',
                    'pr.updated_at'
                )
                ->whereIn('pr.patientID', $patientIds);

            // Apply date filters
            if (!empty($dateFrom)) {
                $prescriptionsQuery->whereDate('pr.created_at', '>=', $dateFrom);
            }

            if (!empty($dateTo)) {
                $prescriptionsQuery->whereDate('pr.created_at', '<=', $dateTo);
            }

            // Apply PD system filter
            if ($system !== 'all') {
                $prescriptionsQuery->where('pr.pd_system', $system);
            }

            $prescriptions = $prescriptionsQuery->orderBy('pr.created_at', 'desc')
                ->get()
                ->groupBy('patientID');

            // Get medicines for all prescriptions
            $prescriptionIds = collect($prescriptions)->flatten()->pluck('id')->toArray();
            
            $medicines = [];
            if (!empty($prescriptionIds)) {
                $medicines = DB::table('prescription_medicine as pm')
                    ->select(
                        'pm.prescription_id',
                        'pm.dosage',
                        'pm.frequency',
                        'pm.duration',
                        'pm.instructions',
                        'm.name as medicine_name'
                    )
                    ->leftJoin('medicines as m', 'pm.medicine_id', '=', 'm.id')
                    ->whereIn('pm.prescription_id', $prescriptionIds)
                    ->get()
                    ->groupBy('prescription_id');
            }

            // Combine patients with their prescriptions and medicines
            $patientsWithPrescriptions = [];
            foreach ($patients as $patient) {
                $patientPrescriptions = isset($prescriptions[$patient->patientID]) ? 
                    $prescriptions[$patient->patientID]->map(function($prescription) use ($medicines) {
                        $prescriptionMedicines = isset($medicines[$prescription->id]) ? 
                            $medicines[$prescription->id]->map(function($medicine) {
                                return [
                                    'medicine_name' => $medicine->medicine_name,
                                    'dosage' => $medicine->dosage,
                                    'frequency' => $medicine->frequency,
                                    'duration' => $medicine->duration,
                                    'instructions' => $medicine->instructions
                                ];
                            })->toArray() : [];

                        return [
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
                            'medicines' => $prescriptionMedicines
                        ];
                    })->toArray() : [];

                $patientsWithPrescriptions[] = [
                    'patientID' => $patient->patientID,
                    'name' => trim($patient->name),
                    'hospitalNumber' => $patient->hospitalNumber,
                    'date_of_birth' => $patient->date_of_birth,
                    'gender' => $patient->gender,
                    'email' => $patient->email,
                    'legalRepresentative' => $patient->legalRepresentative,
                    'situationStatus' => $patient->situationStatus,
                    'prescriptions' => $patientPrescriptions
                ];
            }

            // Filter out patients with no prescriptions if there are active filters
            if (!empty($dateFrom) || !empty($dateTo) || $system !== 'all') {
                $patientsWithPrescriptions = array_filter($patientsWithPrescriptions, function($patient) {
                    return !empty($patient['prescriptions']);
                });
            }

            return response()->json([
                'success' => true,
                'prescriptions' => array_values($patientsWithPrescriptions)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch prescriptions: ' . $e->getMessage()
            ], 500);
        }
    }
}