<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $primaryKey = 'order_itemID';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'orderID',
        'supplyID',
        'quantity',
        'unit_price',
        'total_price',
        'created_at'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'quantity' => 'integer'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'orderID', 'orderID');
    }

    public function medicalSupply()
    {
        return $this->belongsTo(MedicalSupply::class, 'supplyID', 'supplyID');
    }
}