<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmployeeArchiveController extends Controller
{
    /**
     * Archive an employee (permanently delete from users table)
     */
    public function archiveEmployee(Request $request, $userID): JsonResponse
    {
        DB::beginTransaction();

        try {
            // Get authenticated user
            $archivedBy = auth()->id();

            // Get employee data
            $employee = DB::table('users')->where('userID', $userID)->first();
            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            // Check if user is a patient (case-insensitive)
            $userLevel = strtolower($employee->userLevel);
            if ($userLevel === 'patient') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot archive patient accounts. Use patient archive instead.'
                ], 400);
            }

            // Archive employee data
            DB::table('archive')->insert([
                'archived_data' => json_encode($employee),
                'archived_from_table' => 'users',
                'archived_by' => $archivedBy,
                'archived_date' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Permanently delete employee from users table
            DB::table('users')->where('userID', $userID)->delete();

            DB::commit();

            // Log audit trail using AuditLog model
            $this->logAudit($archivedBy, "Archived and permanently deleted employee: {$employee->first_name} {$employee->last_name} (UserID: {$userID}, Role: {$employee->userLevel})");

            return response()->json([
                'success' => true,
                'message' => 'Employee archived and removed from system successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error archiving employee: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to archive employee',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get archived employees
     */
    public function getArchivedEmployees(Request $request): JsonResponse
    {
        try {
            $archives = DB::table('archive')
                ->select('archive.*', 'users.first_name as archived_by_name')
                ->leftJoin('users', 'archive.archived_by', '=', 'users.userID')
                ->where('archive.archived_from_table', 'users')
                ->orderBy('archive.archived_date', 'desc')
                ->paginate(20);

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
            Log::error('Error fetching archived employees: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch archived employees',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore archived employee (recreate in users table)
     */
    public function restoreEmployee($archiveId): JsonResponse
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

            // Check if employee already exists
            $existingEmployee = DB::table('users')
                ->where('userID', $archivedData['userID'])
                ->first();

            if ($existingEmployee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee already exists in system'
                ], 409);
            }

            // Restore employee by inserting back into users table
            DB::table('users')->insert($archivedData);

            // Delete the archive record after restoration
            DB::table('archive')->where('archive_id', $archiveId)->delete();

            DB::commit();

            // Log audit trail using AuditLog model
            $this->logAudit(auth()->id(), "Restored employee: {$archivedData['first_name']} {$archivedData['last_name']} (ArchiveID: {$archiveId})");

            return response()->json([
                'success' => true,
                'message' => 'Employee restored successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error restoring employee: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore employee',
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