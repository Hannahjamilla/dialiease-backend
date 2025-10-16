<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use HasFactory;

    protected $table = 'reminders';
    protected $primaryKey = 'id';

    protected $fillable = [
        'text',
        'date',
        'completed'
    ];

    protected $casts = [
        'completed' => 'boolean',
        'date' => 'date:Y-m-d'
    ];
}