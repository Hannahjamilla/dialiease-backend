<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArduinoController extends Controller
{
    private $arduinoIp = 'http://192.168.135.92'; // Replace with your Arduino's IP
    
    public function startMonitoring(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'target_volume' => 'required|numeric|min:1000|max:2000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $response = Http::get($this->arduinoIp . '/command', [
                'start' => 1,
                'target' => $request->target_volume
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Monitoring started',
                    'arduino_response' => $response->body()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to communicate with Arduino',
                'response' => $response->body()
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start monitoring',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function stopMonitoring(Request $request)
    {
        try {
            $response = Http::get($this->arduinoIp . '/command', [
                'stop' => 1
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Monitoring stopped',
                    'arduino_response' => $response->body()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to communicate with Arduino',
                'response' => $response->body()
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to stop monitoring',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCurrentWeight(Request $request)
    {
        try {
            $response = Http::get($this->arduinoIp . '/weight');

            if ($response->successful()) {
                $weight = (float) $response->body();
                
                return response()->json([
                    'success' => true,
                    'weight' => $weight,
                    'unit' => 'mL'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to get weight from Arduino',
                'response' => $response->body()
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get current weight',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}