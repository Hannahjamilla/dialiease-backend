<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function getDashboardStats(Request $request)
    {
        try {
            // Log::info('Dashboard stats request initiated', [
            //     'user_id' => $request->user()->id,
            //     'email' => $request->user()->email
            // ]);

            // Get counts with explicit database connection
            $doctorCount = User::on('mysql')
                ->where('userLevel', 'doctor')
                ->count();

            $pediatricCount = User::on('mysql')
                ->where('userLevel', 'patient')
                ->whereNotNull('date_of_birth')
                ->where('date_of_birth', '!=', '0000-00-00')
                ->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 18')
                ->count();

            $adultCount = User::on('mysql')
                ->where('userLevel', 'patient')
                ->whereNotNull('date_of_birth')
                ->where('date_of_birth', '!=', '0000-00-00')
                ->whereRaw('TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) >= 18')
                ->count();

            Log::info('Dashboard stats calculated', [
                'doctors' => $doctorCount,
                'pediatric' => $pediatricCount,
                'adult' => $adultCount
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'doctorCount' => $doctorCount,
                    'pediatricCount' => $pediatricCount,
                    'adultCount' => $adultCount
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard stats error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
