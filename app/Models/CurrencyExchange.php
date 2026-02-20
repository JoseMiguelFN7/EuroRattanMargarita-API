<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyExchange extends Model
{
    protected $fillable = [
        'currency_id', 
        'rate', 
        'valid_at'
    ];

    protected $casts = [
        'valid_at' => 'datetime', // Convierte el string de la BD a Carbon automáticamente
        'rate'     => 'decimal:4', // Asegura precisión de 4 decimales al leerlo
    ];

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
