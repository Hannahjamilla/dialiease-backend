<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // Get authenticated user's profile
    public function getProfile()
    {
        $user = Auth::user();
        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }

    // Update user profile
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'suffix' => 'nullable|string|max:10',
            'date_of_birth' => 'sometimes|required|date',
            'gender' => 'sometimes|required|string|in:male,female,other',
            'phone_number' => 'sometimes|required|string|max:20',
            'specialization' => 'nullable|string|max:255',
            'EmpAddress' => 'sometimes|required|string|max:255',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except('profile_image');

        // Handle profile image upload
        if ($request->hasFile('profile_image')) {
            // Delete old image if exists
            if ($user->profile_image) {
                Storage::delete('public/profile_images/' . $user->profile_image);
            }

            $image = $request->file('profile_image');
            $imageName = time() . '_' . $user->userID . '.' . $image->getClientOriginalExtension();
            $image->storeAs('public/profile_images', $imageName);
            $data['profile_image'] = $imageName;
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'user' => $user->fresh()
        ]);
    }

    // Get user by ID (for admin views)
    public function getUser($id)
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $user = User::findOrFail($id);
        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }
}