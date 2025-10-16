<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadyMedicine extends Model
{
    use HasFactory;

    protected $table = 'ready_medicines';

    protected $fillable = [
        'medicine_name',
        'dosage',
        'frequency',
        'duration',
        'instructions',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];
}