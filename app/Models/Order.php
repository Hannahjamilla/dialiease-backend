<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $primaryKey = 'orderID';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'userID',
        'patientID',
        'total_amount',
        'subtotal',
        'discount_percentage',
        'discount_amount',
        'payment_method',
        'payment_status',
        'order_status',
        'payment_reference',
        'order_date',
        'scheduled_pickup_date'
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'scheduled_pickup_date' => 'date',
        'total_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'discount_percentage' => 'decimal:2'
    ];

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'orderID', 'orderID');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class, 'orderID', 'orderID');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patientID', 'patientID');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userID', 'userID');
    }
}