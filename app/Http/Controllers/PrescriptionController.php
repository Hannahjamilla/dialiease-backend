<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PDF;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class PrescriptionController extends Controller
{
    public function index()
    {
        $medicines = Medicine::all();
        return response()->json([
            'success' => true,
            'medicines' => $medicines
        ]);
    }

    public function storeMedicine(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:medicines,name',
            'generic_name' => 'required|string|max:255|unique:medicines,generic_name',
            'description' => 'nullable|string',
            'category' => 'required|string|max:255',
            'manufacturer' => 'required|string|max:255',
            'dosage_form' => 'nullable|string|max:100',
            'strength' => 'nullable|string|max:100'
        ], [
            'name.unique' => 'This brand name medication already exists',
            'generic_name.unique' => 'This generic compound is already registered'
        ]);

        $medicine = Medicine::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Pharmaceutical successfully registered',
            'medicine' => $medicine
        ]);
    }

}