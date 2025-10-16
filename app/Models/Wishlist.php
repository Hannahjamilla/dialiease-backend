<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    use HasFactory;

    protected $primaryKey = 'wishlistID';
    public $timestamps = false;

    protected $fillable = [
        'userID',
        'supplyID',
        'added_at'
    ];

    protected $casts = [
        'added_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userID');
    }

    public function medicalSupply()
    {
        return $this->belongsTo(MedicalSupply::class, 'supplyID');
    }
}