<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProviderController extends Controller
{
   public function generateNumbers(Request $request)
{
    $validator = Validator::make($request->all(), [
        'userLevel' => 'required|string|max:50' // Changed from 'in:doctor,...' to accept any string
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => $validator->errors()->first()
        ], 422);
    }

    $prefix = $this->getPrefix($request->userLevel);
    $employeeNumber = $this->generateUniqueEmployeeNumber($prefix);
    $regNumber = $this->generateUniqueRegistrationNumber($prefix);

    return response()->json([
        'success' => true,
        'employeeNumber' => $employeeNumber,
        'reg_number' => $regNumber
    ]);
}

public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'phone_number' => 'required|string|max:20|unique:users,phone_number|regex:/^[0-9]{10,15}$/',
        'gender' => 'required|in:male,female,other',
        'userLevel' => 'required|string|in:doctor,nurse,staff,admin,distributor,employee',
        'employeeNumber' => 'required|string|max:20|unique:users,employeeNumber',
        'reg_number' => 'required|string|max:50|unique:users,reg_number',
        'specialization' => 'nullable|string|max:255',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422);
    }

    DB::beginTransaction();

    try {
        $userData = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone_number' => $request->phone_number,
            'employeeNumber' => $request->employeeNumber,
            'gender' => $request->gender,
            'userLevel' => $request->userLevel,
            'specialization' => $request->specialization,
            'reg_number' => $request->reg_number,
            'password' => null, // Explicitly set to null
            'EmpStatus' => 'pre-register',
            'status' => 'inactive',
            'email' => $this->generateTemporaryEmail($request->first_name, $request->last_name),
        ];

        $user = User::create($userData);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Provider created successfully',
            'data' => $user
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Database error: ' . (env('APP_DEBUG') ? $e->getMessage() : 'Please contact support'),
        ], 500);
    }
}

private function getPrefix($role)
{
    // Default prefixes for known roles
    $prefixes = [
        'doctor' => 'DOC',
        'nurse' => 'NUR',
        'admin' => 'ADM',
        'staff' => 'STF',
        'distributor' => 'DST'
    ];

    // For custom roles, use first 3 letters uppercase
    if (!array_key_exists(strtolower($role), $prefixes)) {
        return strtoupper(substr($role, 0, 3));
    }

    return $prefixes[strtolower($role)];
}

    private function generateUniqueEmployeeNumber($prefix)
    {
        do {
            $number = $prefix . '-' . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (User::where('employeeNumber', $number)->exists());

        return $number;
    }

    private function generateUniqueRegistrationNumber($prefix)
    {
        do {
            $number = $prefix . '-REG-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::where('reg_number', $number)->exists());

        return $number;
    }

    private function generateTemporaryEmail($firstName, $lastName)
    {
        $base = Str::slug($firstName . '.' . $lastName);
        $email = "{$base}@temp.example";
        
        // Ensure uniqueness
        $counter = 1;
        while (User::where('email', $email)->exists()) {
            $email = "{$base}{$counter}@temp.example";
            $counter++;
        }

        return $email;
    }
}