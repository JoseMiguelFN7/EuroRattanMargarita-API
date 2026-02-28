<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        'emitted_at',
        'igtf_amount',
        'verification_token',
        'paid_amount'
    ];

    protected $casts = [
        'emitted_at' => 'datetime',
        'exempt_amount' => 'decimal:2',
        'tax_base_amount' => 'decimal:2',
        'tax_percentage' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'igtf_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2'
    ];

    protected static function booted()
    {
        static::creating(function ($invoice) {
            if (empty($invoice->verification_token)) {
                $invoice->verification_token = (string) Str::uuid();
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
