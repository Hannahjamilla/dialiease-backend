<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
{
    protected $table = 'treatment'; // Table name
    protected $primaryKey = 'Treatment_ID'; // Primary key

    protected $fillable = [
        'patientID', 'TreatmentStatus', 'treatmentDate', 'bagSerialNumber',
        'solutionImage', 'dry_night', 'IN_ID', 'OUT_ID', 'Balances'
    ];

    protected $casts = [
        'dry_night' => 'boolean',
        'treatmentDate' => 'datetime'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patientID');
    }

public function inSolution()
{
    return $this->belongsTo(Insolution::class, 'IN_ID', 'IN_ID');
}

    public function outSolution()
{
    return $this->belongsTo(Outsolution::class, 'OUT_ID', 'OUT_ID');
}
}
