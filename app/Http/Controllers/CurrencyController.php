<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Currency;
use Illuminate\Support\Facades\Validator;

class CurrencyController extends Controller
{
    /**
     * Listar todas las monedas con su tasa ACTUAL
     */
    public function index()
    {
        // append('current_rate') activa el accessor getCurrentRateAttribute del modelo
        return Currency::all()->append('current_rate');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:3|unique:currencies,code',
            'symbol' => 'required|string|max:5',
            'name' => 'required|string|max:50',
            'is_primary' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        // Lógica de moneda primaria única
        if ($request->is_primary ?? false) {
            Currency::where('is_primary', true)->update(['is_primary' => false]);
        }

        $currency = Currency::create([
            'code' => $request->code,
            'symbol' => $request->symbol,
            'name' => $request->name,
            'is_primary' => $request->is_primary,
        ]);

        // Devolvemos la moneda creada con su tasa (que será 0 o 1 si es primaria)
        return response()->json($currency->append('current_rate'), 201);
    }

    public function update(Request $request, Currency $currency)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'string|size:3|unique:currencies,code,' . $currency->id,
            'symbol' => 'string|max:5',
            'name' => 'string|max:50',
            'is_primary' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()->messages()
            ], 422);
        }

        if ($request->is_primary ?? false) {
            Currency::where('id', '!=', $currency->id)->update(['is_primary' => false]);
        }

        $currency->update([
            'code' => $request->code,
            'symbol' => $request->symbol,
            'name' => $request->name,
            'is_primary' => $request->is_primary,
        ]);

        return response()->json($currency->append('current_rate'));
    }

    public function destroy(Currency $currency)
    {
        if ($currency->paymentMethods()->exists()) {
            return response()->json(['error' => 'No puedes borrar una moneda que tiene métodos de pago asociados.'], 409);
        }

        if ($currency->is_primary){
            return response()->json(['error' => 'No puedes borrar la moneda principal.'], 409);
        }

        $currency->delete();
        return response()->json(['message' => 'Moneda eliminada']);
    }
}
