<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use App\Mail\EmailVerification;
use Illuminate\Support\Facades\Cache;

class OTPController extends Controller
{
    public function updateEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employeeNumber' => 'required|string|exists:users,employeeNumber',
            'newEmail' => 'required|email|unique:users,email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $user = User::where('employeeNumber', $request->employeeNumber)
                   ->where('EmpStatus', 'pre-register')
                   ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found or already registered'
            ], 404);
        }

        // Generate 6-digit numeric OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP in cache for 5 minutes
        Cache::put('email_verification:' . $request->newEmail, [
            'otp' => $otp,
            'employeeNumber' => $request->employeeNumber
        ], now()->addMinutes(5));

        // Send email with OTP
        try {
            Mail::to($request->newEmail)->send(new EmailVerification($otp));
            
            // Update email temporarily (will be confirmed after OTP verification)
            $user->email = $request->newEmail;
            $user->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Verification code sent to your email'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send verification email: ' . $e->getMessage()
            ], 500);
        }
    }

public function verifyOTP(Request $request)
{
    $validator = Validator::make($request->all(), [
        'employeeNumber' => 'required|string|exists:users,employeeNumber',
        'newEmail' => 'required|email',
        'otp' => 'required|digits:6'
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => $validator->errors()->first()
        ], 422);
    }

    $cachedData = Cache::get('email_verification:' . $request->newEmail);

    if (!$cachedData || $cachedData['employeeNumber'] !== $request->employeeNumber) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired verification code'
        ], 400);
    }

    if ($cachedData['otp'] !== $request->otp) {
        return response()->json([
            'success' => false,
            'message' => 'Invalid verification code'
        ], 400);
    }

    // Clear the OTP from cache
    Cache::forget('email_verification:' . $request->newEmail);

    // Update user record without email_verified_at
    $user = User::where('employeeNumber', $request->employeeNumber)->first();
    $user->email = $request->newEmail;
    $user->EmpStatus = 'email-verified'; // Or whatever status you want to use
    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'Email verified successfully'
    ]);
}
    public function resendOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employeeNumber' => 'required|string|exists:users,employeeNumber',
            'newEmail' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $user = User::where('employeeNumber', $request->employeeNumber)
                   ->where('EmpStatus', 'pre-register')
                   ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found or already registered'
            ], 404);
        }

        // Generate new 6-digit numeric OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store OTP in cache for 5 minutes
        Cache::put('email_verification:' . $request->newEmail, [
            'otp' => $otp,
            'employeeNumber' => $request->employeeNumber
        ], now()->addMinutes(5));

        // Send email with new OTP
        try {
            Mail::to($request->newEmail)->send(new EmailVerification($otp));
            
            return response()->json([
                'success' => true,
                'message' => 'New verification code sent to your email'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend verification email: ' . $e->getMessage()
            ], 500);
        }
    }
}