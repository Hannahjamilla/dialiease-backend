<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabResult extends Model
{
    use HasFactory;

    protected $primaryKey = 'labResults_ID';
    protected $table = 'labresults';

    protected $fillable = [
    'userID',
    'patient_ID',
    'prescription_file',
    'prescription_text',
    'prescription_date'
];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patientID', 'patientID');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'userID', 'userID');
    }
}