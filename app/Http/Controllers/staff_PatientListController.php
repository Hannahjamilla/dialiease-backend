<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class staff_PatientListController extends Controller
{
    // Get all patients (both active and pending)
    public function index()
    {
        $patients = DB::table('users')
            ->join('patients', 'users.userID', '=', 'patients.userID')
            ->where('users.userLevel', 'patient')
            ->whereIn('patients.AccStatus', ['active', 'pending', 'confirmed'])
            ->select(
                'users.userID',
                'users.first_name',
                'users.middle_name',
                'users.last_name',
                'users.email',
                'users.date_of_birth',
                'users.gender',
                'users.phone_number',
                'users.EmpAddress',
                'users.created_at',
                'patients.hospitalNumber',
                'patients.AccStatus as status'
            )
            ->orderBy('users.created_at', 'desc')
            ->get();

        return response()->json($patients);
    }

    // Archive a patient
    public function archivePatient($id)
    {
        DB::beginTransaction();

        try {
            // Verify patient exists and is active or pending
            $patientExists = DB::table('patients')
                ->where('userID', $id)
                ->whereIn('AccStatus', ['active', 'pending', 'confirmed'])
                ->exists();

            if (!$patientExists) {
                return response()->json(['error' => 'Patient not found or already archived'], 404);
            }

            // Archive patient
            DB::table('patients')
                ->where('userID', $id)
                ->update(['AccStatus' => 'inactive']);

            // Create audit log
            DB::table('audit_logs')->insert([
                'action' => 'Patient archived',
                'user_id' => Auth::id(),
                'patient_id' => $id,
                'created_at' => now()
            ]);

            DB::commit();

            return response()->json(['message' => 'Patient archived successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to archive patient'], 500);
        }
    }
}