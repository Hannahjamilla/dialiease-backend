<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificationCodeMail;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // First check if user exists
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            $this->logAudit(null, "Failed login attempt - email: {$credentials['email']}");
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                  ->header('Access-Control-Allow-Credentials', 'true');
        }

        // Check if it's an employee type and has pre-register status
        $employeeTypes = ['staff', 'doctor', 'admin', 'nurse', 'distributor'];
        if (in_array($user->userLevel, $employeeTypes)) {
            if ($user->EmpStatus === 'pre-register' || $user->EmpStatus === 'pre-registered') {
                // Check if password matches the temporary password
                if (Hash::check($credentials['password'], $user->password)) {
                    $token = $user->createToken('api-token')->plainTextToken;
                    
                    $this->logAudit($user->userID, "Logged in with pre-register status");
                    
                    return response()->json([
                        'token' => $token,
                        'user' => $user,
                        'requires_credential_change' => true,
                        'message' => 'Please update your credentials to complete registration'
                    ])->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                      ->header('Access-Control-Allow-Credentials', 'true');
                } else {
                    $this->logAudit($user->userID, "Failed login attempt - invalid temporary password");
                    return response()->json([
                        'message' => 'Invalid temporary password'
                    ], 401)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                          ->header('Access-Control-Allow-Credentials', 'true');
                }
            }
            
            // Check if account is inactive
            if ($user->status === 'inactive') {
                return response()->json([
                    'message' => 'Your account is inactive. Please contact administrator.'
                ], 403)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                      ->header('Access-Control-Allow-Credentials', 'true');
            }
        }

        // Regular authentication for active users
        if (!Auth::attempt($credentials)) {
            $this->logAudit($user->userID, "Failed login attempt - invalid password");
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                  ->header('Access-Control-Allow-Credentials', 'true');
        }

        $user = Auth::user();
        
        // Final check if user is active
        if ($user->status === 'inactive') {
            Auth::logout();
            return response()->json([
                'message' => 'Your account is not active. Please contact administrator.'
            ], 403)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                  ->header('Access-Control-Allow-Credentials', 'true');
        }

        $token = $user->createToken('api-token')->plainTextToken;

        $this->logAudit($user->userID, "Successful login");

        return response()->json([
            'token' => $token,
            'user' => $user,
            'requires_credential_change' => false
        ])->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
          ->header('Access-Control-Allow-Credentials', 'true');
    }

    public function sendVerificationCode(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->userID . ',userID'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                  ->header('Access-Control-Allow-Credentials', 'true');
        }

        // Check if email is different from current
        if ($user->email === $request->email) {
            return response()->json([
                'message' => 'Please enter a new email address different from your current one'
            ], 422)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                  ->header('Access-Control-Allow-Credentials', 'true');
        }

        try {
            // Generate verification code
            $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Store verification code and timestamp
            $user->specialization = json_encode([
                'verification_code' => $verificationCode,
                'verification_sent_at' => now()->toDateTimeString(),
                'pending_email' => $request->email
            ]);
            $user->save();

            // Send verification email
            Mail::to($request->email)->send(new VerificationCodeMail($verificationCode));

            $this->logAudit($user->userID, "Verification code sent to new email: " . $request->email);

            return response()->json([
                'message' => 'Verification code sent successfully',
                'verification_sent' => true
            ])->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
              ->header('Access-Control-Allow-Credentials', 'true');

        } catch (\Exception $e) {
            Log::error('Verification code sending error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to send verification code. Please try again.'
            ], 500)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                  ->header('Access-Control-Allow-Credentials', 'true');
        }
    }

    public function verifyEmail(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'verification_code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                  ->header('Access-Control-Allow-Credentials', 'true');
        }

        try {
            // Retrieve verification data
            $verificationData = json_decode($user->specialization, true);
            
            if (!$verificationData || 
                !isset($verificationData['verification_code']) || 
                !isset($verificationData['verification_sent_at']) ||
                !isset($verificationData['pending_email'])) {
                return response()->json([
                    'message' => 'No verification code found. Please request a new one.'
                ], 422)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                      ->header('Access-Control-Allow-Credentials', 'true');
            }

            // Check if verification code matches
            if ($verificationData['verification_code'] !== $request->verification_code) {
                $this->logAudit($user->userID, "Failed email verification - invalid code");
                return response()->json([
                    'message' => 'Invalid verification code'
                ], 422)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                      ->header('Access-Control-Allow-Credentials', 'true');
            }

            // Check if code is expired (15 minutes)
            $verificationSentAt = \Carbon\Carbon::parse($verificationData['verification_sent_at']);
            if (now()->diffInMinutes($verificationSentAt) > 15) {
                $this->logAudit($user->userID, "Failed email verification - code expired");
                return response()->json([
                    'message' => 'Verification code has expired. Please request a new one.'
                ], 422)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                      ->header('Access-Control-Allow-Credentials', 'true');
            }

            // Check if email matches
            if ($verificationData['pending_email'] !== $request->email) {
                $this->logAudit($user->userID, "Failed email verification - email mismatch");
                return response()->json([
                    'message' => 'Email does not match the one we sent the code to'
                ], 422)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                      ->header('Access-Control-Allow-Credentials', 'true');
            }

            $this->logAudit($user->userID, "Email verified successfully: " . $request->email);

            return response()->json([
                'message' => 'Email verified successfully',
                'email_verified' => true
            ])->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
              ->header('Access-Control-Allow-Credentials', 'true');

        } catch (\Exception $e) {
            Log::error('Email verification error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to verify email'
            ], 500)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                  ->header('Access-Control-Allow-Credentials', 'true');
        }
    }

    public function activateAccount(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'verification_code' => 'required|string|size:6',
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/',
            'new_password_confirmation' => 'required'
        ], [
            'new_password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (@$!%*?&)'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                  ->header('Access-Control-Allow-Credentials', 'true');
        }

        try {
            // Verify current temporary password first
            if (!Hash::check($request->current_password, $user->password)) {
                $this->logAudit($user->userID, "Account activation failed - invalid current password");
                return response()->json([
                    'message' => 'Current password is incorrect'
                ], 422)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                      ->header('Access-Control-Allow-Credentials', 'true');
            }

            // Retrieve verification data
            $verificationData = json_decode($user->specialization, true);
            
            if (!$verificationData || 
                !isset($verificationData['verification_code']) || 
                !isset($verificationData['verification_sent_at']) ||
                !isset($verificationData['pending_email'])) {
                return response()->json([
                    'message' => 'No verification code found. Please request a new one.'
                ], 422)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                      ->header('Access-Control-Allow-Credentials', 'true');
            }

            // Check if verification code matches
            if ($verificationData['verification_code'] !== $request->verification_code) {
                $this->logAudit($user->userID, "Account activation failed - invalid verification code");
                return response()->json([
                    'message' => 'Invalid verification code'
                ], 422)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                      ->header('Access-Control-Allow-Credentials', 'true');
            }

            // Check if code is expired (15 minutes)
            $verificationSentAt = \Carbon\Carbon::parse($verificationData['verification_sent_at']);
            if (now()->diffInMinutes($verificationSentAt) > 15) {
                $this->logAudit($user->userID, "Account activation failed - code expired");
                return response()->json([
                    'message' => 'Verification code has expired. Please request a new one.'
                ], 422)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                      ->header('Access-Control-Allow-Credentials', 'true');
            }

            // Check if email matches
            if ($verificationData['pending_email'] !== $request->email) {
                $this->logAudit($user->userID, "Account activation failed - email mismatch");
                return response()->json([
                    'message' => 'Email does not match the one we sent the code to'
                ], 422)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                      ->header('Access-Control-Allow-Credentials', 'true');
            }

            // Update all credentials and activate account
            $user->email = $request->email;
            $user->password = Hash::make($request->new_password);
            $user->EmpStatus = 'active';
            $user->status = 'active';
            $user->specialization = null; // Clear verification data
            $user->save();

            // Revoke all existing tokens
            $user->tokens()->delete();

            $this->logAudit($user->userID, "Registration completed - account activated");

            return response()->json([
                'message' => 'Registration completed successfully. Your account is now active. Please login with your new credentials.',
                'registration_completed' => true,
                'account_activated' => true
            ])->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
              ->header('Access-Control-Allow-Credentials', 'true');

        } catch (\Exception $e) {
            Log::error('Account activation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to complete registration: ' . $e->getMessage()
            ], 500)->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
                  ->header('Access-Control-Allow-Credentials', 'true');
        }
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $userId = $user ? $user->userID : null;
        
        $request->user()->currentAccessToken()->delete();

        $this->logAudit($userId, "Logged out");

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ])->header('Access-Control-Allow-Origin', 'https://dialiease-4un0.onrender.com')
          ->header('Access-Control-Allow-Credentials', 'true');
    }

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