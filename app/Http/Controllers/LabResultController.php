<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\LabResult;

class LabResultController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'lab_result' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'patient_id' => 'required|integer'
        ]);

        try {
            $file = $request->file('lab_result');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            
            // Store the file
            $path = $file->store('lab-results', 'public');
            
            // Create lab result record
            LabResult::create([
                'userID' => Auth::id(),
                'patientID' => $request->patient_id,
                'filename' => $originalName,
                'fileType' => $extension
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lab result uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload lab result'
            ], 500);
        }
    }
}