<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Outsolution extends Model
{
    use HasFactory;

    protected $primaryKey = 'OUT_ID';
    protected $table = 'outsolution';
    public $timestamps = false;

    protected $fillable = [
        'DrainStarted',
        'DrainFinished',
        'VolumeOut',
        'Color',
        'Notes'
    ];

    public function treatment()
    {
        return $this->hasOne(Treatments::class, 'OUT_ID', 'OUT_ID');
    }
}