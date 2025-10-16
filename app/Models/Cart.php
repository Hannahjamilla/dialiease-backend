<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $primaryKey = 'cartID';
    
    // Specify the actual table name
    protected $table = 'cart';
    
    protected $fillable = [
        'userID',
        'supplyID',
        'quantity'
    ];

    protected $casts = [
        'quantity' => 'integer'
    ];

    // If your timestamps have different column names
    const CREATED_AT = 'added_at';
    const UPDATED_AT = 'updated_at';

    public function user()
    {
        return $this->belongsTo(User::class, 'userID');
    }

    public function medicalSupply()
    {
        return $this->belongsTo(MedicalSupply::class, 'supplyID');
    }
}