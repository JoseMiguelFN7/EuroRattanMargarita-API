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
        'image'
    ];

    protected $casts = [
        'bank_details' => 'array',    // Se convierte automágicamente a array al leer
        'requires_proof' => 'boolean',
        'is_active' => 'boolean',
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
