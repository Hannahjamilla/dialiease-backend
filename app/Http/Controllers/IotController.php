<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class IotController extends Controller
{
    public function health()
    {
        return response()->json(['status' => 'healthy'])
               ->header('Access-Control-Allow-Origin', '*')
               ->header('Cache-Control', 'no-cache, must-revalidate');
    }

    public function getStatus()
    {
        try {
            $status = Cache::get('iot_treatment_status', 'inactive');
            return response()->json(['status' => $status])
                   ->header('Access-Control-Allow-Origin', '*')
                   ->header('Cache-Control', 'no-cache, must-revalidate');
        } catch (\Exception $e) {
            return response()->json(['error' => 'Service unavailable'], 503)
                   ->header('Access-Control-Allow-Origin', '*');
        }
    }

    public function updateStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,inactive',
            'device_id' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422)->header('Access-Control-Allow-Origin', '*');
        }

        try {
            $data = $validator->validated();
            Cache::put('iot_treatment_status', $data['status'], now()->addHours(1));
            
            // Update device status as active when treatment status changes
            Cache::put('iot_device_status', [
                'connected' => true,
                'device_id' => $data['device_id'],
                'last_seen' => now()->toDateTimeString()
            ], now()->addMinutes(5));

            return response()->json(['success' => true])
                   ->header('Access-Control-Allow-Origin', '*');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status'
            ], 500)->header('Access-Control-Allow-Origin', '*');
        }
    }

    public function getWeight()
    {
        try {
            $weightData = Cache::get('iot_latest_weight', [
                'weight' => 0,
                'volume_ml' => 0,
                'volume_kl' => 0,
                'timestamp' => null,
                'is_start' => false,
                'device_id' => null
            ]);

            return response()->json($weightData)
                   ->header('Access-Control-Allow-Origin', '*')
                   ->header('Cache-Control', 'no-cache, must-revalidate');
        } catch (\Exception $e) {
            return response()->json([
                'weight' => 0,
                'volume_ml' => 0,
                'volume_kl' => 0,
                'error' => 'Failed to retrieve weight data'
            ], 503)->header('Access-Control-Allow-Origin', '*');
        }
    }

    public function storeWeight(Request $request)
{
    $validator = Validator::make($request->all(), [
        'weight' => 'required|numeric|min:0|max:5000',
        'device_id' => 'required|string|max:50'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        $data = $validator->validated();
        $weight = $data['weight'];
        $volumeMl = $weight * 1000; // Convert kg to ml
        $volumeKl = $volumeMl / 1000000; // Convert ml to kl

        // Store the raw weight data
        Cache::put('iot_latest_weight', [
            'weight' => $weight,
            'volume_ml' => $volumeMl,
            'volume_kl' => $volumeKl,
            'timestamp' => now()->toDateTimeString(),
            'is_start' => ($weight > 0),
            'device_id' => $data['device_id']
        ], now()->addMinutes(1));

        // Update device status
        Cache::put('iot_device_status', [
            'connected' => true,
            'device_id' => $data['device_id'],
            'last_seen' => now()->toDateTimeString()
        ], now()->addMinutes(5));

        return response()->json(['success' => true]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to store weight data'
        ], 500);
    }
}

public function getInitialWeight()
{
    try {
        $weightData = Cache::get('iot_latest_weight', [
            'weight' => 0,
            'volume_ml' => 0,
            'volume_kl' => 0,
            'timestamp' => null
        ]);

        return response()->json([
            'success' => true,
            'initial_weight' => $weightData['weight'],
            'initial_volume' => $weightData['volume_ml']
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve initial weight'
        ], 500);
    }
}

    public function updateDeviceStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'connected' => 'required|boolean',
            'device_id' => 'required|string|max:50'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422)->header('Access-Control-Allow-Origin', '*');
        }

        try {
            $data = $validator->validated();
            
            Cache::put('iot_device_status', [
                'connected' => $data['connected'],
                'device_id' => $data['device_id'],
                'last_seen' => now()->toDateTimeString()
            ], now()->addMinutes(5));

            return response()->json(['success' => true])
                   ->header('Access-Control-Allow-Origin', '*');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update device status'
            ], 500)->header('Access-Control-Allow-Origin', '*');
        }
    }

    public function getDeviceStatus()
    {
        try {
            $status = Cache::get('iot_device_status', [
                'connected' => false,
                'device_id' => null,
                'last_seen' => null
            ]);

            return response()->json($status)
                   ->header('Access-Control-Allow-Origin', '*')
                   ->header('Cache-Control', 'no-cache, must-revalidate');
        } catch (\Exception $e) {
            return response()->json([
                'connected' => false,
                'error' => 'Failed to retrieve device status'
            ], 503)->header('Access-Control-Allow-Origin', '*');
        }
    }

    public function connectDevice(Request $request)
    {
        // Rate limiting to prevent brute force
        $executed = RateLimiter::attempt(
            'iot-connect:'.$request->ip(),
            $perMinute = 5,
            function() {}
        );

        if (!$executed) {
            return response()->json([
                'success' => false,
                'message' => 'Too many connection attempts'
            ], 429)->header('Access-Control-Allow-Origin', '*');
        }

        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422)->header('Access-Control-Allow-Origin', '*');
            }

            $data = $validator->validated();
            
            Cache::put('iot_device_status', [
                'connected' => true,
                'device_id' => $data['device_id'],
                'last_seen' => now()->toDateTimeString()
            ], now()->addMinutes(5));

            return response()->json([
                'success' => true,
                'message' => 'Device connected successfully'
            ])->header('Access-Control-Allow-Origin', '*');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', '*');
        }
    }

    public function getReminders(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422)->header('Access-Control-Allow-Origin', '*');
            }

            // In a real application, you would fetch reminders from database
            // Here we'll simulate some reminders based on treatment status
            $treatmentStatus = Cache::get('iot_treatment_status', 'inactive');
            $reminders = [];

            if ($treatmentStatus === 'active') {
                $elapsedMinutes = Carbon::now()->diffInMinutes(
                    Carbon::parse(Cache::get('iot_treatment_started_at', now()))
                );

                if ($elapsedMinutes > 30 && $elapsedMinutes < 35) {
                    $reminders[] = "Reminder: Check your catheter site for any signs of infection";
                }

                if ($elapsedMinutes > 60) {
                    $reminders[] = "Reminder: Treatment should be completing soon. Prepare for drainage";
                }
            }

            return response()->json([
                'success' => true,
                'reminders' => count($reminders) > 0 ? $reminders[array_rand($reminders)] : ""
            ])->header('Access-Control-Allow-Origin', '*');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get reminders'
            ], 500)->header('Access-Control-Allow-Origin', '*');
        }
    }
}