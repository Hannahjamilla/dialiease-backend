<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Insolution extends Model
{
    use HasFactory;

    protected $primaryKey = 'IN_ID';
    protected $table = 'insolution';
    public $timestamps = false;

    protected $fillable = [
        'InStarted',
        'InFinished',
        'VolumeIn',
        'Dialysate',
        'Dwell'
    ];

    public function treatment()
    {
        return $this->hasOne(Treatments::class, 'IN_ID', 'IN_ID');
    }
}