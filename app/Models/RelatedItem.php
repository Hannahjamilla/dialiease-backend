<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RelatedItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplyID',
        'related_supplyID',
        'relation_type'
    ];

    public function medicalSupply()
    {
        return $this->belongsTo(MedicalSupply::class, 'supplyID');
    }

    public function relatedSupply()
    {
        return $this->belongsTo(MedicalSupply::class, 'related_supplyID');
    }
}