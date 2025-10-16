<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\PasswordResetOtp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ForgotPasswordController extends Controller
{
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ], [
            'email.exists' => 'The provided email is not registered with us.'
        ]);

        $user = User::where('email', $request->email)->first();

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpExpires = Carbon::now()->addMinutes(10);

        // Save OTP to user record
        $user->update([
            'reset_token' => $otp,
            'reset_expires' => $otpExpires
        ]);

        // Send OTP email
        try {
            Mail::to($user->email)->send(new PasswordResetOtp($otp));
        } catch (\Exception $e) {
            Log::error('Failed to send OTP email: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to send OTP email. Please try again later.'
            ], 500);
        }

        return response()->json([
            'message' => 'OTP sent successfully',
            'email' => $user->email
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6'
        ]);

        $user = User::where('email', $request->email)->first();

        // Check if OTP matches and is not expired
        if (!$user->reset_token || $user->reset_token !== $request->otp) {
            return response()->json([
                'message' => 'Invalid OTP',
                'errors' => ['otp' => ['The provided OTP is invalid.']]
            ], 422);
        }

        if (Carbon::now()->gt($user->reset_expires)) {
            return response()->json([
                'message' => 'Expired OTP',
                'errors' => ['otp' => ['The provided OTP has expired.']]
            ], 422);
        }

        return response()->json([
            'message' => 'OTP verified successfully',
            'email' => $user->email,
            'otp' => $request->otp
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'otp' => 'required|digits:6',
            'newPassword' => 'required|min:6|confirmed'
        ], [
            'newPassword.confirmed' => 'Password confirmation does not match.'
        ]);

        $user = User::where('email', $request->email)->first();

        // Verify OTP first
        if (!$user->reset_token || $user->reset_token !== $request->otp) {
            return response()->json([
                'message' => 'Invalid OTP',
                'errors' => ['otp' => ['The provided OTP is invalid.']]
            ], 422);
        }

        if (Carbon::now()->gt($user->reset_expires)) {
            return response()->json([
                'message' => 'Expired OTP',
                'errors' => ['otp' => ['The provided OTP has expired.']]
            ], 422);
        }

        // Check if new password is different from current password
        if (Hash::check($request->newPassword, $user->password)) {
            return response()->json([
                'message' => 'New password must be different from current password',
                'errors' => ['newPassword' => ['New password must be different from current password']]
            ], 422);
        }

        // Update password and clear reset token
        $user->update([
            'password' => Hash::make($request->newPassword),
            'reset_token' => null,
            'reset_expires' => null
        ]);

        return response()->json([
            'message' => 'Password reset successfully'
        ]);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        $user = User::where('email', $request->email)->first();

        // Generate new 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $otpExpires = Carbon::now()->addMinutes(10);

        // Update user record with new OTP
        $user->update([
            'reset_token' => $otp,
            'reset_expires' => $otpExpires
        ]);

        // Resend OTP email
        try {
            Mail::to($user->email)->send(new PasswordResetOtp($otp));
        } catch (\Exception $e) {
            Log::error('Failed to resend OTP email: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to resend OTP email. Please try again later.'
            ], 500);
        }

        return response()->json([
            'message' => 'OTP resent successfully',
            'email' => $user->email
        ]);
    }
}