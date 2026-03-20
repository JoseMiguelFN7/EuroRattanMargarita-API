<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\Currency;
use App\Models\CurrencyExchange;
use App\Jobs\FetchBcvRateJob;

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

    /**
     * Histórico paginado para la tabla
     */
    public function getHistoryTable(Request $request)
    {
        // 1. Buscamos la moneda VES
        $vesCurrency = Currency::where('code', 'VES')->first();

        if (!$vesCurrency) {
            return response()->json(['message' => 'Moneda VES no encontrada'], 404);
        }

        $perPage = $request->input('per_page', 10);

        // 2. Consultamos el historial ordenado desde el más reciente
        $history = CurrencyExchange::where('currency_id', $vesCurrency->id)
            ->orderBy('valid_at', 'desc')
            ->paginate($perPage);

        // 3. Transformamos la salida para que coincida exactamente con lo que pide tu frontend
        $history->through(function ($record) {
            return [
                'exchange_rate' => (float) $record->rate,
                'valid_at'      => $record->valid_at->format('Y-m-d H:i:s'), // Formato estándar
            ];
        });

        return response()->json($history);
    }

    /**
     * Endpoint 2: Datos para la gráfica (Últimos 30 días)
     */
    public function getHistoryChart()
    {
        $vesCurrency = Currency::where('code', 'VES')->first();

        if (!$vesCurrency) {
            return response()->json(['message' => 'Moneda VES no encontrada'], 404);
        }

        // 1. Definimos la fecha de corte (hace 30 días)
        $startDate = Carbon::now()->subDays(30)->startOfDay();

        // 2. Consultamos en orden ascendente (cronológico para la gráfica)
        $rates = CurrencyExchange::where('currency_id', $vesCurrency->id)
            ->where('valid_at', '>=', $startDate)
            ->orderBy('valid_at', 'asc')
            ->get();

        // Arreglo manual para garantizar el español sin depender de la configuración del servidor
        $mesesEspanol = [
            1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun',
            7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'
        ];

        // 3. Formateamos la respuesta
        $chartData = $rates->map(function ($record) use ($mesesEspanol) {
            $dia = $record->valid_at->format('d'); // "26"
            $mes = $mesesEspanol[$record->valid_at->month]; // "ene"

            return [
                'displayDate' => $dia . ' ' . $mes,
                'rate'        => (float) $record->rate
            ];
        });

        return response()->json($chartData);
    }

    public function syncBcvAsync()
    {
        $userId = auth('sanctum')->id();

        // 1. Despachamos el trabajo a la cola (background), pasándole quién lo pidió
        FetchBcvRateJob::dispatch($userId);

        // 2. Respondemos al instante para no bloquear el Frontend
        return response()->json([
            'message' => 'La sincronización se está ejecutando en segundo plano. Recibirás una notificación en breve.'
        ], 202); 
    }
}
