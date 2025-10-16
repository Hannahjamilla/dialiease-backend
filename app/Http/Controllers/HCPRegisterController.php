<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class HCPRegisterController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employeeNumber' => 'required|string|exists:users,employeeNumber',
            'specialization' => 'required|string|max:255',
            'Doc_license' => 'required|string|max:255|unique:users,Doc_license',
            'newPassword' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/',      // Must contain at least one uppercase letter
                'regex:/[0-9]/',      // Must contain at least one number
                'regex:/[^A-Za-z0-9]/' // Must contain at least one special character
            ]
        ], [
            'newPassword.regex' => 'The password must contain at least one uppercase letter, one number, and one special character.'
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

        // Update user with registration data
        $updateData = [
            'specialization' => $request->specialization,
            'Doc_license' => $request->Doc_license,
            'password' => Hash::make($request->newPassword),
            'EmpStatus' => 'registered',
            'status' => 'active'
        ];

        $user->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. You can now login with your new password.',
            'data' => $user->makeHidden(['password'])
        ]);
    }
}