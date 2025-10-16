<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Patient extends Model
{
    protected $primaryKey = 'patientID';
    public $incrementing = true;
    protected $keyType = 'integer';
    
    protected $fillable = [
    'userID',
    'hospitalNumber',
    'address',
    'legalRepresentative',
    'AccStatus',
    'TermsAndCondition',
    'situationStatus',
];



    public $timestamps = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userID', 'userID')
                    ->withDefault([
                        'first_name' => 'Unknown',
                        'last_name' => 'Patient',
                        'email' => null
                    ]);
    }

    public function treatments()
    {
        return $this->hasMany(Treatment::class, 'patientID', 'patientID');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'patient_id', 'patientID');
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class, 'patient_ID', 'patientID');
    }
}