<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DwellTimeOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'hours',
        'description',
    ];

    protected $casts = [
        'hours' => 'float',
    ];
}