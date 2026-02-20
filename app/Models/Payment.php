<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'currency_id',
        'payment_method_id',
        'amount',
        'reference_number',
        'proof_image',
        'status',
        'verified_at',
        'exchange_rate'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }
    
    // --- Helper para la URL de la imagen ---
    public function getProofImageUrlAttribute()
    {
        return $this->proof_image 
            ? asset('storage/' . $this->proof_image) 
            : null;
    }
}
