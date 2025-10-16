<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmpStatus extends Model
{
    use HasFactory;

    protected $primaryKey = 'statusID';
    protected $table = 'emp_status';

    protected $fillable = [
        'userID',
        'status',
        'remarks'
    ];
}