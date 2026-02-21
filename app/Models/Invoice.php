<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'order_id',
        'invoice_number',
        'control_number',
        'client_name',
        'client_document',
        'client_address',
        'exempt_amount',
        'tax_base_amount',
        'tax_percentage',
        'tax_amount',
        'total_amount',
        'pdf_url',
        'emitted_at'
    ];

    protected $casts = [
        'emitted_at' => 'datetime',
        'exempt_amount' => 'decimal:2',
        'tax_base_amount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
