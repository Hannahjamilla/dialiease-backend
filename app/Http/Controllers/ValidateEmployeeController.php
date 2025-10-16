<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ValidateEmployeeController extends Controller
{
    public function validateEmployee(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employeeNumber' => 'required|string|exists:users,employeeNumber',
            'registrationCode' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $user = User::where('employeeNumber', $request->employeeNumber)
                   ->where('reg_number', $request->registrationCode)
                   ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid employee number or registration code'
            ], 404);
        }

        if ($user->EmpStatus !== 'pre-register') {
            return response()->json([
                'success' => false,
                'message' => 'Employee already registered'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Validation successful',
            'data' => [
                'employeeNumber' => $user->employeeNumber,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
                'email' => $user->email,
                'EmpStatus' => $user->EmpStatus
            ]
        ]);
    }
}