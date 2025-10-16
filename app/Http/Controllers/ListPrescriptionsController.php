<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListPrescriptionsController extends Controller
{
    public function index()
    {
        try {
            $prescriptions = Prescription::with([
                    'patient.user:userID,first_name,last_name',
                    'user:userID,first_name,last_name'
                ])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(
                $prescriptions->map(function ($prescription) {
                    $fileUrl = $this->getPrescriptionUrl($prescription);
                    $patient = $prescription->patient;
                    $issuer = $prescription->user;

                    return [
                        'id' => $prescription->id,
                        'patient_id' => $prescription->patientID,
                        'patient_name' => $patient && $patient->user
                            ? ($patient->user->first_name . ' ' . $patient->user->last_name)
                            : 'Unknown Patient',
                        'hospital_number' => $patient ? $patient->hospitalNumber : 'N/A',
                        'guardian_name' => $patient ? $patient->legalRepresentative : null,
                        'issued_by' => $issuer 
                            ? ($issuer->first_name . ' ' . $issuer->last_name) 
                            : 'Unknown Doctor',
                        'file_url' => $fileUrl,
                        'file_name' => basename($prescription->prescription_file),
                        'created_at' => $prescription->created_at->toDateTimeString(),
                        'is_blob' => !empty($prescription->prescription_blob)
                    ];
                })
            );

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Server Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function viewFile($id): StreamedResponse
    {
        $prescription = Prescription::findOrFail($id);

        // If stored as blob
        if (!empty($prescription->prescription_blob)) {
            return response()->streamDownload(
                function() use ($prescription) {
                    echo $prescription->prescription_blob;
                },
                'prescription.pdf',
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline'
                ]
            );
        }

        // If stored as file
        if (Storage::exists($prescription->prescription_file)) {
            return Storage::response(
                $prescription->prescription_file,
                'prescription.pdf',
                [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'inline'
                ]
            );
        }

        abort(404, 'Prescription file not found');
    }

    private function getPrescriptionUrl($prescription): string
    {
        return url("/prescription/view/{$prescription->id}");
    }
}