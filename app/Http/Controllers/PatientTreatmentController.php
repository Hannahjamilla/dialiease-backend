 <?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Treatment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;


class PatientTreatmentController extends Controller
{
    public function getTreatments(Request $request)
    {
        $user = Auth::user();
        $patientId = $user->patient->id;

        $query = Treatment::with(['inSolution', 'outSolution'])
            ->where('Patient_ID', $patientId)
            ->orderBy('TreatmentDate', 'desc');

        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('TreatmentStatus', 'like', "%$search%")
                  ->orWhereHas('inSolution', function($q) use ($search) {
                      $q->where('Dialysate', 'like', "%$search%");
                  })
                  ->orWhereHas('outSolution', function($q) use ($search) {
                      $q->where('Notes', 'like', "%$search%");
                  });
            });
        }

        // Apply status filter
        if ($request->has('status') && !empty($request->status)) {
            $query->where('TreatmentStatus', $request->status);
        }

        // Apply date range filter
        if ($request->has('dateFrom') && !empty($request->dateFrom)) {
            $query->whereDate('TreatmentDate', '>=', $request->dateFrom);
        }
        if ($request->has('dateTo') && !empty($request->dateTo)) {
            $query->whereDate('TreatmentDate', '<=', $request->dateTo);
        }

        // Paginate results
        $perPage = 10;
        $treatments = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'treatments' => $treatments->items(),
            'totalPages' => $treatments->lastPage(),
            'currentPage' => $treatments->currentPage()
        ]);
    }

    public function getTreatmentStats()
    {
        $user = Auth::user();
        $patientId = $user->patient->id;

        // Get basic stats
        $totalTreatments = Treatment::where('Patient_ID', $patientId)->count();
        $lastTreatment = Treatment::where('Patient_ID', $patientId)
            ->orderBy('TreatmentDate', 'desc')
            ->first();

        // Get average volume in and balance for completed treatments
        $completedTreatments = Treatment::with(['inSolution', 'outSolution'])
            ->where('Patient_ID', $patientId)
            ->where('TreatmentStatus', 'Completed')
            ->get();

        $avgVolumeIn = $completedTreatments->avg(function($treatment) {
            return $treatment->inSolution->VolumeIn ?? 0;
        });

        $avgBalance = $completedTreatments->avg(function($treatment) {
            return ($treatment->outSolution->VolumeOut ?? 0) - ($treatment->inSolution->VolumeIn ?? 0);
        });

        return response()->json([
            'success' => true,
            'stats' => [
                'totalTreatments' => $totalTreatments,
                'avgVolumeIn' => round($avgVolumeIn, 1),
                'avgBalance' => round($avgBalance, 1),
                'lastTreatmentDate' => $lastTreatment ? $lastTreatment->TreatmentDate : null
            ]
        ]);
    }
}
