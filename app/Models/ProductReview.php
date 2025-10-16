<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    use HasFactory;

    protected $primaryKey = 'reviewID';
    public $incrementing = true;

    protected $fillable = [
        'userID',
        'supplyID',
        'rating',
        'comment',
        'status',
        'is_anonymous'
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_anonymous' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userID', 'userID');
    }

    public function supply()
    {
        return $this->belongsTo(MedicalSupply::class, 'supplyID', 'supplyID');
    }
}