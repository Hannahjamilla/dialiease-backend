<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Treatments extends Model
{
    use HasFactory;

    protected $primaryKey = 'Treatment_ID';
    protected $table = 'treatment';
    public $timestamps = false;

    protected $fillable = [
        'patientID',
        'IN_ID',
        'OUT_ID',
        'TreatmentStatus',
        'Balances',
        'treatmentDate',
        'bagSerialNumber',
        'solutionImage',
        'dry_night'
    ];

    public function inSolution()
    {
        return $this->belongsTo(Insolution::class, 'IN_ID', 'IN_ID');
    }

    public function outSolution()
    {
        return $this->belongsTo(Outsolution::class, 'OUT_ID', 'OUT_ID');
    }

    public function patient()
    {
        return $this->belongsTo(User::class, 'patientID', 'userID');
    }
}