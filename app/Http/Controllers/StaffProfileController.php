<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class StaffProfileController extends Controller
{
    public function getProfile()
    {
        $user = Auth::user();
        
        // Ensure the user is staff/nurse
        if (!in_array($user->userLevel, ['staff', 'nurse'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($user);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        // Validate the request data
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:10',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->userID.',userID',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string|max:50',
            'phone_number' => 'nullable|string|max:20',
            'specialization' => 'nullable|string|max:255',
            'EmpAddress' => 'nullable|string|max:500',
            'EmpStatus' => 'nullable|string|max:50',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update user data
        $user->first_name = $request->input('first_name');
        $user->middle_name = $request->input('middle_name');
        $user->last_name = $request->input('last_name');
        $user->suffix = $request->input('suffix');
        $user->email = $request->input('email');
        $user->date_of_birth = $request->input('date_of_birth');
        $user->gender = $request->input('gender');
        $user->phone_number = $request->input('phone_number');
        $user->specialization = $request->input('specialization');
        $user->EmpAddress = $request->input('EmpAddress');
        $user->EmpStatus = $request->input('EmpStatus');

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            // Delete old image if exists
            if ($user->profile_image) {
                Storage::delete('public/profile_images/' . $user->profile_image);
            }

            // Store new image
            $image = $request->file('profile_image');
            $imageName = time() . '_' . $user->userID . '.' . $image->getClientOriginalExtension();
            $image->storeAs('public/profile_images', $imageName);
            $user->profile_image = 'profile_images/' . $imageName;
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
            'profile_image' => $user->profile_image
        ]);
    }
}