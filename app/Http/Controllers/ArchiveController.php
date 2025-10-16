<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Patient;
use App\Models\Archive;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ArchiveController extends Controller
{
    /**
     * Archive a patient and associated user
     */
    public function archivePatient(Request $request, $userID): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Get authenticated user
            $archivedBy = auth()->id();

            // Get user data
            $user = DB::table('users')->where('userID', $userID)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Get patient data
            $patient = DB::table('patients')->where('userID', $userID)->first();
            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            // Archive user data
            DB::table('archive')->insert([
                'archived_data' => json_encode($user),
                'archived_from_table' => 'users',
                'archived_by' => $archivedBy,
                'archived_date' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Archive patient data
            DB::table('archive')->insert([
                'archived_data' => json_encode($patient),
                'archived_from_table' => 'patients',
                'archived_by' => $archivedBy,
                'archived_date' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Permanently delete from original tables
            DB::table('patients')->where('userID', $userID)->delete();
            DB::table('users')->where('userID', $userID)->delete();

            DB::commit();

            // Log audit trail using AuditLog model
            $this->logAudit($archivedBy, "Archived patient: {$user->first_name} {$user->last_name} (UserID: {$userID})");

            return response()->json([
                'success' => true,
                'message' => 'Patient archived successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error archiving patient: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to archive patient',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get archived records
     */
    public function getArchivedRecords(Request $request): JsonResponse
    {
        try {
            $query = DB::table('archive')
                ->select('archive.*', 'users.first_name', 'users.last_name')
                ->leftJoin('users', 'archive.archived_by', '=', 'users.userID')
                ->orderBy('archive.archived_date', 'desc');

            // Filter by table if provided
            if ($request->has('table')) {
                $query->where('archive.archived_from_table', $request->table);
            }

            $archives = $query->paginate(20);

            // Decode JSON data for each archive record
            $archives->getCollection()->transform(function ($archive) {
                $archive->archived_data = json_decode($archive->archived_data);
                return $archive;
            });

            return response()->json([
                'success' => true,
                'data' => $archives
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching archived records: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch archived records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore archived record
     */
    public function restoreArchive($archiveId): JsonResponse
    {
        DB::beginTransaction();

        try {
            $archive = DB::table('archive')->where('archive_id', $archiveId)->first();
            
            if (!$archive) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archive record not found'
                ], 404);
            }

            $archivedData = json_decode($archive->archived_data, true);

            // Restore based on original table
            switch ($archive->archived_from_table) {
                case 'users':
                    // Check if user already exists
                    $existingUser = DB::table('users')
                        ->where('userID', $archivedData['userID'])
                        ->first();

                    if ($existingUser) {
                        return response()->json([
                            'success' => false,
                            'message' => 'User already exists and cannot be restored'
                        ], 409);
                    }

                    // Restore user by inserting back
                    DB::table('users')->insert($archivedData);
                    break;
                    
                case 'patients':
                    // Check if patient already exists
                    $existingPatient = DB::table('patients')
                        ->where('patientID', $archivedData['patientID'])
                        ->first();

                    if ($existingPatient) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Patient already exists and cannot be restored'
                        ], 409);
                    }

                    // Restore patient by inserting back
                    DB::table('patients')->insert($archivedData);
                    break;
            }

            // Delete the archive record after restoration
            DB::table('archive')->where('archive_id', $archiveId)->delete();

            DB::commit();

            // Log audit trail using AuditLog model
            $this->logAudit(auth()->id(), "Restored archived record from {$archive->archived_from_table} (ArchiveID: {$archiveId})");

            return response()->json([
                'success' => true,
                'message' => 'Record restored successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error restoring archive: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Audit log method using AuditLog model
     */
    private function logAudit($userID, $action)
    {
        try {
            AuditLog::create([
                'userID' => $userID,
                'action' => $action,
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log audit trail: ' . $e->getMessage());
        }
    }
}