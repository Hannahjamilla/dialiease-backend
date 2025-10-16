<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use PDF;
use App\Mail\ProviderCredentialsMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ADMIN_ADDHCproviderController extends Controller
{
    public function checkDocLicense($license)
    {
        try {
            if (!$license || $license === 'null' || $license === 'undefined') {
                return response()->json(['is_unique' => true]);
            }
            
            $existing = User::where('Doc_license', $license)->first();
            return response()->json([
                'is_unique' => !$existing
            ]);
        } catch (\Exception $e) {
            Log::error('License check error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error checking license'
            ], 500);
        }
    }

    public function preRegisterHCprovider(Request $request)
    {
        Log::info('Pre-registration request received', $request->all());

        // First check if employee already exists
        $existingUser = User::where('first_name', $request->first_name)
            ->where('last_name', $request->last_name)
            ->when($request->filled('middle_name'), function($query) use ($request) {
                return $query->where('middle_name', $request->middle_name);
            })
            ->when($request->filled('suffix'), function($query) use ($request) {
                return $query->where('suffix', $request->suffix);
            })
            ->first();

        if ($existingUser) {
            Log::warning('Employee already exists', ['name' => $request->first_name . ' ' . $request->last_name]);
            return response()->json([
                'message' => 'Registration failed',
                'error' => 'This employee is already registered in the system'
            ], 409);
        }

        // Check if license number already exists (if provided or required)
        if ($request->filled('Doc_license')) {
            $existingLicense = User::where('Doc_license', $request->Doc_license)->first();
            if ($existingLicense) {
                Log::warning('License already exists', ['license' => $request->Doc_license]);
                return response()->json([
                    'message' => 'Registration failed',
                    'error' => 'License number already exists'
                ], 409);
            }
        }

        // Custom validation rules based on userLevel
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:users,email',
            'employeeNumber' => 'required|string|unique:users,employeeNumber',
            'userLevel' => 'required|string',
            'specialization' => 'nullable|string|max:255',
            'Doc_license' => 'nullable|string|max:100|unique:users,Doc_license',
        ]);

        // Add conditional validation for Doc_license based on userLevel
        $validator->sometimes('Doc_license', 'required', function ($input) {
            return $input->userLevel === 'doctor' || $input->userLevel === 'nurse';
        });

        if ($validator->fails()) {
            Log::warning('Validation failed', ['errors' => $validator->errors()->toArray()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $password = Str::random(12);
            
            $userData = [
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name ?? null,
                'last_name' => $request->last_name,
                'suffix' => $request->suffix ?? null,
                'email' => $request->email ?? null,
                'employeeNumber' => $request->employeeNumber,
                'password' => Hash::make($password),
                'phone_number' => '',
                'specialization' => $request->specialization ?? null,
                'EmpStatus' => 'pre-register',
                'status' => 'inactive',
                'Doc_license' => $request->Doc_license ?? null,
                'userLevel' => $request->userLevel,
                'TodaysStatus' => 'off duty',
            ];

            $user = User::create($userData);
            
            // Log audit with safe authentication check
            $adminId = auth()->check() ? auth()->id() : 1;
            $this->logAudit($adminId, 'Pre-registered healthcare provider: ' . $user->first_name . ' ' . $user->last_name);

            Log::info('User created successfully', ['userID' => $user->userID, 'password' => $password]);

            return response()->json([
                'message' => 'Healthcare provider pre-registered successfully',
                'userID' => $user->userID,
                'employeeNumber' => $user->employeeNumber,
                'password' => $password
            ], 201);

        } catch (\Exception $e) {
            Log::error('Registration Error: '.$e->getMessage());
            Log::error('Stack trace: '.$e->getTraceAsString());
            return response()->json([
                'message' => 'Registration failed',
                'error' => 'Internal server error. Please try again.'
            ], 500);
        }
    }

    public function bulkRegisterHCproviders(Request $request)
    {
        Log::info('Bulk registration request received', ['count' => count($request->input('employees', []))]);

        $employees = $request->input('employees', []);
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        if (empty($employees)) {
            return response()->json([
                'message' => 'No employees provided for bulk registration',
                'success_count' => 0,
                'error_count' => 0,
                'results' => []
            ], 400);
        }

        foreach ($employees as $index => $employee) {
            try {
                // Skip if essential fields are missing
                if (empty($employee['first_name']) || empty($employee['last_name'])) {
                    $results[] = [
                        'employee' => $employee,
                        'status' => 'failed',
                        'message' => 'First name and last name are required'
                    ];
                    $errorCount++;
                    continue;
                }

                // Check if employee already exists
                $existingUser = User::where('first_name', $employee['first_name'])
                    ->where('last_name', $employee['last_name'])
                    ->when(!empty($employee['middle_name']), function($query) use ($employee) {
                        return $query->where('middle_name', $employee['middle_name']);
                    })
                    ->when(!empty($employee['suffix']), function($query) use ($employee) {
                        return $query->where('suffix', $employee['suffix']);
                    })
                    ->first();

                if ($existingUser) {
                    $results[] = [
                        'employee' => $employee,
                        'status' => 'skipped',
                        'message' => 'Employee already registered'
                    ];
                    $errorCount++;
                    continue;
                }

                // Check if license number already exists
                if (!empty($employee['Doc_license'])) {
                    $existingLicense = User::where('Doc_license', $employee['Doc_license'])->first();
                    if ($existingLicense) {
                        $results[] = [
                            'employee' => $employee,
                            'status' => 'skipped',
                            'message' => 'License number already exists'
                        ];
                        $errorCount++;
                        continue;
                    }
                }

                $validator = Validator::make($employee, [
                    'first_name' => 'required|string|max:255',
                    'last_name' => 'required|string|max:255',
                    'email' => 'nullable|email|unique:users,email',
                    'userLevel' => 'required|string',
                    'specialization' => 'nullable|string|max:255',
                    'Doc_license' => 'nullable|string|max:100|unique:users,Doc_license',
                ]);

                // Add conditional validation for Doc_license based on userLevel
                $validator->sometimes('Doc_license', 'required', function ($input) {
                    return !empty($input['userLevel']) && ($input['userLevel'] === 'doctor' || $input['userLevel'] === 'nurse');
                });

                if ($validator->fails()) {
                    $results[] = [
                        'employee' => $employee,
                        'status' => 'failed',
                        'message' => $validator->errors()->first()
                    ];
                    $errorCount++;
                    continue;
                }

                $password = Str::random(12);
                $employeeNumber = 'EMP-' . mt_rand(100000, 999999);
                
                $userData = [
                    'first_name' => $employee['first_name'],
                    'middle_name' => $employee['middle_name'] ?? null,
                    'last_name' => $employee['last_name'],
                    'suffix' => $employee['suffix'] ?? null,
                    'email' => $employee['email'] ?? null,
                    'employeeNumber' => $employeeNumber,
                    'password' => Hash::make($password),
                    'phone_number' => '',
                    'specialization' => $employee['specialization'] ?? null,
                    'EmpStatus' => 'pre-register',
                    'status' => 'inactive',
                    'Doc_license' => $employee['Doc_license'] ?? null,
                    'userLevel' => $employee['userLevel'],
                    'TodaysStatus' => 'off duty',
                ];

                $user = User::create($userData);
                
                // Log audit with safe authentication check
                $adminId = auth()->check() ? auth()->id() : 1;
                $this->logAudit($adminId, 'Bulk pre-registered healthcare provider: ' . $user->first_name . ' ' . $user->last_name);

                $results[] = [
                    'employee' => $employee,
                    'status' => 'success',
                    'userID' => $user->userID,
                    'employeeNumber' => $employeeNumber,
                    'password' => $password
                ];
                $successCount++;

                Log::info('Bulk user created', ['userID' => $user->userID, 'index' => $index]);

            } catch (\Exception $e) {
                Log::error('Bulk registration error for employee: ' . json_encode($employee) . ' - ' . $e->getMessage());
                $results[] = [
                    'employee' => $employee,
                    'status' => 'failed',
                    'message' => 'Internal server error'
                ];
                $errorCount++;
            }
        }

        Log::info('Bulk registration completed', ['success' => $successCount, 'errors' => $errorCount]);

        return response()->json([
            'message' => 'Bulk registration completed',
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'results' => $results
        ]);
    }

    public function generatePreRegisterPDF(Request $request)
    {
        try {
            Log::info('PDF generation requested', $request->all());
            
            $validator = Validator::make($request->all(), [
                'userID' => 'required|integer|exists:users,userID',
                'password' => 'required|string'
            ]);

            if ($validator->fails()) {
                Log::warning('PDF generation validation failed', ['errors' => $validator->errors()->toArray()]);
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($request->userID);
            $password = $request->password;
            
            Log::info('Generating PDF for user', [
                'userID' => $user->userID,
                'employeeNumber' => $user->employeeNumber,
                'hasPassword' => !empty($password)
            ]);
            
            // Generate PDF with proper error handling
            $pdf = PDF::loadView('pdf.pre_register', [
                'user' => $user,
                'password' => $password
            ])->setPaper('a4', 'portrait');
            
            $filename = 'pre_register_' . $user->employeeNumber . '.pdf';
            
            Log::info('PDF generated successfully', ['filename' => $filename]);
            
            return $pdf->download($filename);
            
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('User not found for PDF generation: ' . $request->userID);
            return response()->json([
                'message' => 'User not found',
                'error' => 'The specified user does not exist'
            ], 404);
        } catch (\Exception $e) {
            Log::error('PDF generation error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to generate PDF',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function listProviders()
    {
        try {
            $providers = User::where('userLevel', '!=', 'patient')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($user) {
                    return [
                        'userID' => $user->userID,
                        'employeeNumber' => $user->employeeNumber,
                        'first_name' => $user->first_name,
                        'middle_name' => $user->middle_name,
                        'last_name' => $user->last_name,
                        'email' => $user->email,
                        'phone_number' => $user->phone_number,
                        'specialization' => $user->specialization,
                        'Doc_license' => $user->Doc_license,
                        'userLevel' => $user->userLevel,
                        'EmpStatus' => $user->EmpStatus ?? 'pre-register',
                        'status' => $user->status ?? 'inactive',
                        'TodaysStatus' => $user->TodaysStatus ?? 'off duty',
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ];
                });

            return response()->json($providers);
        } catch (\Exception $e) {
            Log::error('List providers error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to fetch providers',
                'error' => $e->getMessage()
            ], 500);
        }
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
            Log::error('Audit log error: ' . $e->getMessage());
        }
    }
}