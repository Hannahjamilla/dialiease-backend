<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrescriptionMedicine extends Model
{
    use HasFactory;

    protected $table = 'prescription_medicine';

    protected $fillable = [
        'prescription_id',
        'patientID',
        'userID',
        'medicine_id',
        'dosage',
        'frequency',
        'duration',
        'instructions'
    ];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patientID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userID');
    }
}