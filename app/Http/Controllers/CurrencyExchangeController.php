<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Currency;

class CurrencyExchangeController extends Controller
{
    /**
     * Ver historial de tasas de una moneda
     */
    public function index(Currency $currency)
    {
        return $currency->exchangeRates()->orderBy('valid_at', 'desc')->take(30)->get();
    }

    /**
     * Registrar una NUEVA tasa de cambio (Ej: Actualizar BCV del día)
     */
    public function store(Request $request, Currency $currency)
    {
        // No tiene sentido cambiar la tasa de la moneda base (USD siempre es 1)
        if ($currency->is_primary) {
            return response()->json(['error' => 'No puedes cambiar la tasa de la moneda base.'], 400);
        }

        $request->validate([
            'rate' => 'required|numeric|min:0.00000001',
        ]);

        $exchangeRate = $currency->exchangeRates()->create([
            'rate' => $request->rate,
            'valid_at' => now(), // O puedes dejar que el frontend mande la fecha
        ]);

        return response()->json([
            'message' => 'Tasa actualizada correctamente',
            'data' => $exchangeRate,
            'currency_code' => $currency->code
        ], 201);
    }
    
    /**
     * Obtener la tasa más reciente (Shortcut)
     */
    public function latest(Currency $currency)
    {
        return response()->json([
            'currency' => $currency->code,
            'rate' => $currency->current_rate // Usando el accessor del modelo
        ]);
    }
}
