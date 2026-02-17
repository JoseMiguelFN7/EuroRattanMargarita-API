<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = ['code', 'symbol', 'name', 'is_primary'];

    // Relación con el historial de tasas
    public function exchangeRates()
    {
        return $this->hasMany(CurrencyExchange::class);
    }

    // Relación con los métodos de pago
    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    // Helper: Obtener la tasa actual (último registro)
    public function getCurrentRateAttribute()
    {
        // Si es la moneda primaria (USD), la tasa siempre es 1
        if ($this->is_primary) return 1;

        $latest = $this->exchangeRates()->latest('valid_at')->first();
        return $latest ? $latest->rate : 0;
    }
}
