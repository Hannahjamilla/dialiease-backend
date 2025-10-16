<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;

class View_ScheduleController extends Controller
{
    /**
     * Get all patient schedules
     */
    public function index(Request $request)
    {
        // You can add filters if needed
        $query = Schedule::query();
        
        // Filter by date if provided
        if ($request->has('date')) {
            $query->whereDate('appointment_date', $request->date);
        }
        
        // Filter by confirmation status if provided
        if ($request->has('confirmation_status')) {
            $query->where('confirmation_status', $request->confirmation_status);
        }
        
        // Filter by checkup status if provided
        if ($request->has('checkup_status')) {
            $query->where('checkup_status', $request->checkup_status);
        }
        
        // Order by appointment date
        $schedules = $query->orderBy('appointment_date', 'asc')
                          ->with(['patient']) // Assuming you have a patient relationship
                          ->get();
        
        return response()->json($schedules);
    }

    /**
     * Update schedule status (confirmation or checkup)
     */
    public function updateStatus(Request $request, $schedule_id)
    {
        $request->validate([
            'field' => 'required|in:confirmation_status,checkup_status',
            'value' => 'required|string'
        ]);
        
        $schedule = Schedule::findOrFail($schedule_id);
        $schedule->{$request->field} = $request->value;
        
        if ($request->has('remarks')) {
            $schedule->remarks = $request->remarks;
        }
        
        $schedule->save();
        
        return response()->json($schedule);
    }
}