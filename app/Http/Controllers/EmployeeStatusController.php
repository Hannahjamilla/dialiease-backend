<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Carbon\Carbon;

class EmployeeStatusController extends Controller
{
   public function getEmployeeStatus()
{
    try {
        $employees = User::where('userLevel', '!=', 'patient')
            ->select([
                'userID',
                'first_name',
                'last_name',
                'email',
                'userLevel',
                'TodaysStatus',
                'updated_at as statusUpdatedAt'
            ])
            ->orderByRaw("
                CASE 
                    WHEN LOWER(TRIM(TodaysStatus)) = 'in duty' THEN 1
                    WHEN LOWER(TRIM(TodaysStatus)) = 'on duty' THEN 1
                    WHEN LOWER(TRIM(TodaysStatus)) = 'off duty' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('userLevel')
            ->orderBy('last_name')
            ->get();

        return response()->json([
            'success' => true,
            'employees' => $employees,
            'last_updated' => now()->toDateTimeString()
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve employee status',
            'error' => env('APP_DEBUG') ? $e->getMessage() : null
        ], 500);
    }
}
}