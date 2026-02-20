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
    public function latest($code)
    {
        // 1. Buscar la moneda manualmente
        $currency = Currency::where('code', strtoupper($code))->first();

        if (!$currency) {
            return response()->json(['message' => 'Moneda no encontrada'], 404);
        }

        // 2. Si es la moneda base (USD), la tasa siempre es 1
        if ($currency->code === 'USD' || $currency->is_primary) {
            return response()->json([
                'currency' => $currency->code,
                'rate'     => 1.00,
                'valid_at' => now()->toDateTimeString(),
            ]);
        }

        // 3. Buscar la tasa válida más reciente (Query Manual)
        // Usamos la relación 'currencyExchanges' del modelo Currency
        $latestRate = $currency->exchangeRates()
            ->where('valid_at', '<=', now()) // Ignora tasas futuras (del día 18 si hoy es 17)
            ->orderBy('valid_at', 'desc')   // Ordena de la más nueva a la más vieja
            ->first();

        // 4. Validar si existe tasa
        if (!$latestRate) {
            return response()->json([
                'message' => 'No hay tasa de cambio registrada válida para hoy.'
            ], 404);
        }

        // 5. Retornar respuesta
        return response()->json([
            'currency' => $currency->code,
            'rate'     => (float) $latestRate->rate, // Aseguramos que sea número
            'valid_at' => $latestRate->valid_at->toDateTimeString()
        ]);
    }
}
