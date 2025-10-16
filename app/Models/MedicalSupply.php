<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalSupply extends Model
{
    use HasFactory;

    protected $primaryKey = 'supplyID';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'supplyID',
        'name',
        'description',
        'category',
        'stock',
        'minStock',
        'price',
        'supplier',
        'expiryDate',
        'image',
        'status',
    ];

    /**
     * Scope for active supplies
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Accessor for image URL
     */
    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/assets/images/Medical supplies/' . $this->image);
        }
        return null;
    }

    /**
     * Accessor for low stock status
     */
    public function getIsLowStockAttribute()
    {
        return $this->stock <= $this->minStock;
    }
}