<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class Forcheckup_PatientTreatmentController extends Controller
{
    public function getPatientTreatmentHistory($patientId, Request $request)
    {
        $search = $request->query('search', '');
        $dateFrom = $request->query('date_from', '');
        $dateTo = $request->query('date_to', '');

        // Verify the patient exists
        $patientExists = DB::table('patients')
            ->where('patientID', $patientId)
            ->exists();

        if (!$patientExists) {
            return response()->json([
                'success' => false,
                'message' => 'Patient not found'
            ], 404);
        }

        $query = DB::table('treatment as t')
            ->select(
                't.Treatment_ID',
                't.treatmentDate',
                't.bagSerialNumber',
                't.solutionImage',
                't.dry_night',
                't.TreatmentStatus',
                't.Balances',
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
            ->where('t.patientID', $patientId)
            ->leftJoin('insolution as i', 't.IN_ID', '=', 'i.IN_ID')
            ->leftJoin('outsolution as o', 't.OUT_ID', '=', 'o.OUT_ID')
            ->orderBy('t.treatmentDate', 'desc');

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('t.treatmentDate', 'like', "%$search%")
                  ->orWhere('t.bagSerialNumber', 'like', "%$search%")
                  ->orWhere('t.TreatmentStatus', 'like', "%$search%")
                  ->orWhere('o.Color', 'like', "%$search%");
            });
        }

        if (!empty($dateFrom)) {
            $query->whereDate('t.treatmentDate', '>=', $dateFrom);
        }

        if (!empty($dateTo)) {
            $query->whereDate('t.treatmentDate', '<=', $dateTo);
        }

        $treatments = $query->get();

        // Calculate statistics
        $stats = [
            'total_treatments' => $treatments->count(),
            'completed_treatments' => $treatments->where('TreatmentStatus', 'Completed')->count(),
            'cancelled_treatments' => $treatments->where('TreatmentStatus', 'Cancelled')->count(),
            'dry_nights' => $treatments->where('dry_night', 1)->count(),
            'abnormal_colors' => $treatments->whereNotIn('Color', ['clear', 'yellow', 'amber'])->count(),
        ];

        // Calculate average volumes
        $volumeIn = $treatments->avg('VolumeIn');
        $volumeOut = $treatments->avg('VolumeOut');
        $netUF = $volumeOut - $volumeIn;

        $stats['avg_volume_in'] = round($volumeIn ?? 0);
        $stats['avg_volume_out'] = round($volumeOut ?? 0);
        $stats['avg_net_uf'] = round($netUF ?? 0);

        // Calculate fluid retention cases (where VolumeIn - VolumeOut > 200)
        $fluidRetention = $treatments->filter(function($treatment) {
            return $treatment->VolumeIn && $treatment->VolumeOut && 
                   ($treatment->VolumeIn - $treatment->VolumeOut) > 200;
        })->count();

        $stats['fluid_retention_cases'] = $fluidRetention;

        return response()->json([
            'success' => true,
            'treatments' => $treatments,
            'stats' => $stats
        ]);
    }
}