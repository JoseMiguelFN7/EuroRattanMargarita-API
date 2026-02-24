<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'bank_details',
        'currency_id',
        'requires_proof',
        'is_active',
        'image',
        'applies_igtf'
    ];

    protected $casts = [
        'bank_details' => 'array',
        'requires_proof' => 'boolean',
        'is_active' => 'boolean',
        'applies_igtf' => 'boolean',
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
