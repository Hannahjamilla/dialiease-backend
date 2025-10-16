<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\EmailVerification;
use Illuminate\Validation\Rules;

class RegisterController extends Controller
{
    /**
     * Validate employee credentials (Step 1)
     */
    public function validateEmployee(Request $request)
    {
        $request->validate([
            'employeeNumber' => 'required|string|regex:/^EMP-\d{6}$/',
            'registrationCode' => 'required|string|min:8|max:50'
        ]);

        $user = User::where('employeeNumber', $request->employeeNumber)
                    ->where('reg_number', $request->registrationCode)
                    ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid employee credentials'
            ], 404);
        }

        // Check employee status
        if ($user->EmpStatus === 'registered') {
            return response()->json([
                'success' => false,
                'message' => 'Employee is already registered',
                'employee' => [
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'employeeNumber' => $user->employeeNumber,
                    'status' => $user->EmpStatus,
                    'email' => $user->email // Include current email
                ]
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'userID' => $user->userID,
                'name' => $user->first_name . ' ' . $user->last_name,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'employeeNumber' => $user->employeeNumber,
                'status' => $user->EmpStatus,
                'email' => $user->email // Include current email
            ],
            'message' => 'Employee validated successfully'
        ]);
    }

    /**
     * Send OTP for email verification (Step 2)
     */
   public function sendOTP(Request $request)
{
    $request->validate([
        'email' => [
            'required',
            'email',
            function ($attribute, $value, $fail) use ($request) {
                $user = User::where('employeeNumber', $request->employeeNumber)->first();
                if ($user && $user->email === $value) {
                    $fail('This is already your current email address.');
                }
            },
            'unique:users,email,' . ($request->userID ?? 'NULL') . ',userID'
        ],
        'employeeNumber' => 'required|string|regex:/^EMP-\d{6}$/'
    ]);

        $user = User::where('employeeNumber', $request->employeeNumber)->firstOrFail();

        // Generate and save OTP
        $otp = rand(100000, 999999);
        $expiresAt = now()->addMinutes(15);

        $user->update([
            'otp' => Hash::make($otp),
            'otp_expires_at' => $expiresAt,
            'new_email' => $request->email, // Store new email temporarily
            'email_verification_token' => Str::random(60)
        ]);

        // Send verification email with OTP
        Mail::to($request->email)->send(new EmailVerification($user, $otp));

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'employee' => [
                'name' => $user->first_name . ' ' . $user->last_name,
                'employeeNumber' => $user->employeeNumber
            ],
            'otp' => env('APP_ENV') === 'local' ? $otp : null
        ]);
    }

    /**
     * Verify OTP (Step 2)
     */
    public function verifyOTP(Request $request)
{
    $request->validate([
        'email' => [
            'required',
            'email',
            function ($attribute, $value, $fail) use ($request) {
                $user = User::where('employeeNumber', $request->employeeNumber)
                          ->where('new_email', $value)
                          ->first();
                if (!$user) {
                    $fail('The email address doesn\'t match the one we sent the OTP to.');
                }
            }
        ],
        'employeeNumber' => 'required|string|regex:/^EMP-\d{6}$/',
        'otp' => 'required|digits:6'
    ]);

        $user = User::where('employeeNumber', $request->employeeNumber)
                    ->where('new_email', $request->email) // Check against new_email
                    ->firstOrFail();

        if (!$user->otp || !Hash::check($request->otp, $user->otp)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP',
                'employee' => [
                    'name' => $user->first_name . ' ' . $user->last_name
                ]
            ], 400);
        }

        if ($user->otp_expires_at < now()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired',
                'employee' => [
                    'name' => $user->first_name . ' ' . $user->last_name
                ]
            ], 400);
        }

        // Check if email is already taken by another user
        $emailExists = User::where('email', $user->new_email)
                          ->where('userID', '!=', $user->userID)
                          ->exists();

        if ($emailExists) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already registered by another user',
                'employee' => [
                    'name' => $user->first_name . ' ' . $user->last_name
                ]
            ], 400);
        }

        // Update email and clear verification fields
        $user->update([
            'email' => $user->new_email, // Set the new email
            'new_email' => null, // Clear temporary email
            'otp' => null,
            'otp_expires_at' => null,
            'email_verified_at' => now(),
            'email_verification_token' => null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email verified and updated successfully',
            'employee' => [
                'name' => $user->first_name . ' ' . $user->last_name,
                'employeeNumber' => $user->employeeNumber,
                'email' => $user->email
            ]
        ]);
    }


    /**
     * Complete employee registration (Step 3)
     */
    public function employeeRegister(Request $request)
    {
        $request->validate([
            'userID' => 'required|exists:users,userID',
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'suffix' => 'nullable|string|max:10',
            'date_of_birth' => 'required|date',
            'gender' => 'required|in:male,female,other',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'profile_image' => 'nullable|image|max:2048'
        ]);

        $user = User::findOrFail($request->userID);

        if ($user->EmpStatus === 'registered') {
            return response()->json([
                'success' => false,
                'message' => 'User is already registered',
                'employee' => [
                    'name' => $user->first_name . ' ' . $user->last_name
                ]
            ], 400);
        }

        $updateData = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'middle_name' => $request->middle_name,
            'suffix' => $request->suffix,
            'date_of_birth' => $request->date_of_birth,
            'gender' => $request->gender,
            'password' => Hash::make($request->password),
            'EmpStatus' => 'registered'
        ];

        if ($request->hasFile('profile_image')) {
            $path = $request->file('profile_image')->store('profile_images', 'public');
            $updateData['profile_image'] = $path;
        }

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Employee registered successfully',
            'employee' => [
                'name' => $user->first_name . ' ' . $user->last_name,
                'employeeNumber' => $user->employeeNumber,
                'email' => $user->email
            ]
        ]);
    }

    /**
     * Change credentials for pre-registered users
     */
    public function employeeChangeCredentials(Request $request)
    {
        $request->validate([
            'userID' => 'required|exists:users,userID',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()]
        ]);

        $user = User::findOrFail($request->userID);

        if ($user->EmpStatus !== 'pre-signup') {
            return response()->json([
                'success' => false,
                'message' => 'This action is only for pre-registered users',
                'employee' => [
                    'name' => $user->first_name . ' ' . $user->last_name,
                    'status' => $user->EmpStatus
                ]
            ], 400);
        }

        $verificationToken = Str::random(60);
        
        $user->update([
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verification_token' => $verificationToken,
            'email_verified_at' => null
        ]);
        
        Mail::to($request->email)->send(new EmailVerification($user, $verificationToken));

        return response()->json([
            'success' => true,
            'message' => 'Credentials updated successfully. Please verify your email.',
            'employee' => [
                'name' => $user->first_name . ' ' . $user->last_name,
                'employeeNumber' => $user->employeeNumber
            ]
        ]);
    }

    /**
     * Verify email address
     */
    public function verifyEmail($token)
    {
        $user = User::where('email_verification_token', $token)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification token'
            ], 404);
        }
        
        $user->update([
            'email_verified_at' => now(),
            'email_verification_token' => null,
            'EmpStatus' => 'registered',
            'status' => 'active'
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully. You can now login.',
            'employee' => [
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'employeeNumber' => $user->employeeNumber
            ]
        ]);
    }
}