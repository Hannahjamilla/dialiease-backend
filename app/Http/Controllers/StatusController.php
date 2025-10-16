<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StatusController extends Controller
{
    public function updateStatus(Request $request)
    {
        // Validate the request
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:in Duty,on duty,off duty']
        ]);

        $user = Auth::user();
        
        // Check user level
        if ($user->userLevel === 'patient') {
            return response()->json([
                'success' => false,
                'message' => 'Patients cannot update their status'
            ], 403);
        }

        // Normalize the status
        $status = strtolower(trim($validated['status']));
        $normalizedStatus = $status === 'on duty' ? 'in Duty' : $status;
        
        try {
            // Update user status
            $user->update([
                'TodaysStatus' => $normalizedStatus
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'data' => [
                    'status' => $normalizedStatus,
                    'user_id' => $user->id
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Status update failed for user '.$user->id.': '.$e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}