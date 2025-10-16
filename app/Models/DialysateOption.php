<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DialysateOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'percentage',
        'color',
        'description',
    ];

    protected $casts = [
        'percentage' => 'float',
    ];
}