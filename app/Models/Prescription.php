<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    use HasFactory;

    protected $fillable = [
        'patientID',
        'userID',
        'additional_instructions',
        'pd_system',
        'pd_modality',
        'pd_total_exchanges',
        'pd_fill_volume',
        'pd_dwell_time',
        'pd_exchanges',
        'pd_bag_percentages',
        'pd_bag_counts'
    ];

    protected $casts = [
        'pd_exchanges' => 'array',
        'pd_bag_percentages' => 'array',
        'pd_bag_counts' => 'array'
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patientID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userID');
    }

    public function medicines()
    {
        return $this->hasMany(PrescriptionMedicine::class);
    }
}