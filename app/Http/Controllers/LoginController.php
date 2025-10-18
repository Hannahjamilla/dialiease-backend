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
    /**
     * Handle preflight CORS requests
     */
    private function handleCors()
    {
        header('Access-Control-Allow-Origin: https://dialiease-4un0.onrender.com');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Expose-Headers: X-CSRF-TOKEN');
    }

    /**
     * Create CORS response
     */
    private function corsResponse($data = null, $status = 200)
    {
        $response = response()->json($data, $status);
        
        return $response->withHeaders([
            'Access-Control-Allow-Origin' => 'https://dialiease-4un0.onrender.com',
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Expose-Headers' => 'X-CSRF-TOKEN',
        ]);
    }

    public function login(Request $request)
    {
        // Handle CORS headers
        $this->handleCors();
        
        // Handle preflight OPTIONS request
        if ($request->isMethod('options')) {
            return $this->corsResponse();
        }

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // First check if user exists
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            $this->logAudit(null, "Failed login attempt - email: {$credentials['email']}");
            return $this->corsResponse([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Check if it's an employee type and has pre-register status
        $employeeTypes = ['staff', 'doctor', 'admin', 'nurse', 'distributor'];
        if (in_array($user->userLevel, $employeeTypes)) {
            if ($user->EmpStatus === 'pre-register' || $user->EmpStatus === 'pre-registered') {
                // Check if password matches the temporary password
                if (Hash::check($credentials['password'], $user->password)) {
                    $token = $user->createToken('api-token')->plainTextToken;
                    
                    $this->logAudit($user->userID, "Logged in with pre-register status");
                    
                    return $this->corsResponse([
                        'token' => $token,
                        'user' => $user,
                        'requires_credential_change' => true,
                        'message' => 'Please update your credentials to complete registration'
                    ]);
                } else {
                    $this->logAudit($user->userID, "Failed login attempt - invalid temporary password");
                    return $this->corsResponse([
                        'message' => 'Invalid temporary password'
                    ], 401);
                }
            }
            
            // Check if account is inactive
            if ($user->status === 'inactive') {
                return $this->corsResponse([
                    'message' => 'Your account is inactive. Please contact administrator.'
                ], 403);
            }
        }

        // Regular authentication for active users
        if (!Auth::attempt($credentials)) {
            $this->logAudit($user->userID, "Failed login attempt - invalid password");
            return $this->corsResponse([
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();
        
        // Final check if user is active
        if ($user->status === 'inactive') {
            Auth::logout();
            return $this->corsResponse([
                'message' => 'Your account is not active. Please contact administrator.'
            ], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        $this->logAudit($user->userID, "Successful login");

        return $this->corsResponse([
            'token' => $token,
            'user' => $user,
            'requires_credential_change' => false
        ]);
    }

    public function sendVerificationCode(Request $request)
    {
        // Handle CORS headers
        $this->handleCors();
        
        // Handle preflight OPTIONS request
        if ($request->isMethod('options')) {
            return $this->corsResponse();
        }

        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:users,email,' . $user->userID . ',userID'
        ]);

        if ($validator->fails()) {
            return $this->corsResponse([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if email is different from current
        if ($user->email === $request->email) {
            return $this->corsResponse([
                'message' => 'Please enter a new email address different from your current one'
            ], 422);
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

            return $this->corsResponse([
                'message' => 'Verification code sent successfully',
                'verification_sent' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Verification code sending error: ' . $e->getMessage());
            return $this->corsResponse([
                'message' => 'Failed to send verification code. Please try again.'
            ], 500);
        }
    }

    public function verifyEmail(Request $request)
    {
        // Handle CORS headers
        $this->handleCors();
        
        // Handle preflight OPTIONS request
        if ($request->isMethod('options')) {
            return $this->corsResponse();
        }

        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'verification_code' => 'required|string|size:6'
        ]);

        if ($validator->fails()) {
            return $this->corsResponse([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Retrieve verification data
            $verificationData = json_decode($user->specialization, true);
            
            if (!$verificationData || 
                !isset($verificationData['verification_code']) || 
                !isset($verificationData['verification_sent_at']) ||
                !isset($verificationData['pending_email'])) {
                return $this->corsResponse([
                    'message' => 'No verification code found. Please request a new one.'
                ], 422);
            }

            // Check if verification code matches
            if ($verificationData['verification_code'] !== $request->verification_code) {
                $this->logAudit($user->userID, "Failed email verification - invalid code");
                return $this->corsResponse([
                    'message' => 'Invalid verification code'
                ], 422);
            }

            // Check if code is expired (15 minutes)
            $verificationSentAt = \Carbon\Carbon::parse($verificationData['verification_sent_at']);
            if (now()->diffInMinutes($verificationSentAt) > 15) {
                $this->logAudit($user->userID, "Failed email verification - code expired");
                return $this->corsResponse([
                    'message' => 'Verification code has expired. Please request a new one.'
                ], 422);
            }

            // Check if email matches
            if ($verificationData['pending_email'] !== $request->email) {
                $this->logAudit($user->userID, "Failed email verification - email mismatch");
                return $this->corsResponse([
                    'message' => 'Email does not match the one we sent the code to'
                ], 422);
            }

            $this->logAudit($user->userID, "Email verified successfully: " . $request->email);

            return $this->corsResponse([
                'message' => 'Email verified successfully',
                'email_verified' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Email verification error: ' . $e->getMessage());
            return $this->corsResponse([
                'message' => 'Failed to verify email'
            ], 500);
        }
    }

    public function activateAccount(Request $request)
    {
        // Handle CORS headers
        $this->handleCors();
        
        // Handle preflight OPTIONS request
        if ($request->isMethod('options')) {
            return $this->corsResponse();
        }

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
            return $this->corsResponse([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verify current temporary password first
            if (!Hash::check($request->current_password, $user->password)) {
                $this->logAudit($user->userID, "Account activation failed - invalid current password");
                return $this->corsResponse([
                    'message' => 'Current password is incorrect'
                ], 422);
            }

            // Retrieve verification data
            $verificationData = json_decode($user->specialization, true);
            
            if (!$verificationData || 
                !isset($verificationData['verification_code']) || 
                !isset($verificationData['verification_sent_at']) ||
                !isset($verificationData['pending_email'])) {
                return $this->corsResponse([
                    'message' => 'No verification code found. Please request a new one.'
                ], 422);
            }

            // Check if verification code matches
            if ($verificationData['verification_code'] !== $request->verification_code) {
                $this->logAudit($user->userID, "Account activation failed - invalid verification code");
                return $this->corsResponse([
                    'message' => 'Invalid verification code'
                ], 422);
            }

            // Check if code is expired (15 minutes)
            $verificationSentAt = \Carbon\Carbon::parse($verificationData['verification_sent_at']);
            if (now()->diffInMinutes($verificationSentAt) > 15) {
                $this->logAudit($user->userID, "Account activation failed - code expired");
                return $this->corsResponse([
                    'message' => 'Verification code has expired. Please request a new one.'
                ], 422);
            }

            // Check if email matches
            if ($verificationData['pending_email'] !== $request->email) {
                $this->logAudit($user->userID, "Account activation failed - email mismatch");
                return $this->corsResponse([
                    'message' => 'Email does not match the one we sent the code to'
                ], 422);
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

            return $this->corsResponse([
                'message' => 'Registration completed successfully. Your account is now active. Please login with your new credentials.',
                'registration_completed' => true,
                'account_activated' => true
            ]);

        } catch (\Exception $e) {
            Log::error('Account activation error: ' . $e->getMessage());
            return $this->corsResponse([
                'message' => 'Failed to complete registration: ' . $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        // Handle CORS headers
        $this->handleCors();
        
        // Handle preflight OPTIONS request
        if ($request->isMethod('options')) {
            return $this->corsResponse();
        }

        $user = $request->user();
        $userId = $user ? $user->userID : null;
        
        $request->user()->currentAccessToken()->delete();

        $this->logAudit($userId, "Logged out");

        return $this->corsResponse([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Handle CSRF cookie request - this is the endpoint that's failing
     */
    public function getCsrfCookie(Request $request)
    {
        // Handle CORS headers
        $this->handleCors();
        
        // Handle preflight OPTIONS request
        if ($request->isMethod('options')) {
            return $this->corsResponse();
        }

        // This will set the CSRF cookie
        return $this->corsResponse([
            'message' => 'CSRF cookie set successfully'
        ]);
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