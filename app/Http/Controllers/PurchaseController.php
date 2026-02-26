<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\ProductMovement;
use App\Models\Currency;
use App\Models\CurrencyExchange;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        
        $purchases = Purchase::with(['supplier', 'products']) 
                             ->orderBy('date', 'desc')
                             ->paginate($perPage);

        $purchases->getCollection()->each(function ($purchase) {
            // Agregamos ambos totales generados por los accessors
            $purchase->append(['total', 'total_ves', 'document_url']);
            $purchase->makeHidden('products'); 

            $purchase->supplier->makeHidden(['created_at', 'updated_at']);
        });

        $purchases->makeHidden(['created_at', 'updated_at']);

        return response()->json($purchases);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'supplier_id'         => 'required|exists:suppliers,id',
            'code'                => 'required|string|unique:purchases,code',
            'date'                => 'required|date',
            'notes'               => 'nullable|string',
            'products'            => 'required|array|min:1',
            'products.*.id'       => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.cost'     => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.color_id' => 'nullable|exists:colors,id',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // --- 1. OBTENER TASA HISTÓRICA SEGÚN LA FECHA DE COMPRA ---
        $vesCurrency = Currency::where('code', 'VES')->first();
        $exchangeRate = 0;

        if ($vesCurrency) {
            $rateRecord = CurrencyExchange::where('currency_id', $vesCurrency->id)
                ->where('valid_at', '<=', Carbon::parse($request->date)->endOfDay())
                ->orderBy('valid_at', 'desc')
                ->first();
            
            $exchangeRate = $rateRecord ? $rateRecord->rate : 0;
        }
        // -----------------------------------------------------------

        // --- PROCESAR EL ARCHIVO ---
        $documentPath = null;
        if ($request->hasFile('document')) {
            // Se guardará en storage/app/public/purchases_docs
            $documentPath = $request->file('document')->store('purchases_docs', 'public');
        }

        DB::beginTransaction();

        try {
            // 2. CREAR CABECERA (Guardando la tasa)
            $purchase = Purchase::create([
                'supplier_id'   => $request->supplier_id,
                'code'          => $request->code,
                'date'          => $request->date,
                'notes'         => $request->notes,
                'exchange_rate' => $exchangeRate,
                'document'      => $documentPath
            ]);

            foreach ($request->products as $item) {
                $prodId   = $item['id'];
                $qty      = $item['quantity'];
                $cost     = $item['cost'];
                $discount = $item['discount'] ?? 0;
                $colorId  = $item['color_id'] ?? null; 

                // 3. GUARDAR PIVOTE (Histórico Financiero)
                $purchase->products()->attach($prodId, [
                    'quantity' => $qty,
                    'cost'     => $cost,
                    'discount' => $discount,
                    'color_id' => $colorId
                ]);

                // 4. GUARDAR MOVIMIENTO
                ProductMovement::create([
                    'product_id'        => $prodId,
                    'quantity'          => $qty,
                    'color_id'          => $colorId,
                    'movement_date'     => $request->date,
                    'movementable_id'   => $purchase->id,
                    'movementable_type' => Purchase::class
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Compra registrada correctamente',
                'data' => $purchase->load('products')
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al registrar compra',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $purchase = Purchase::with([
            'supplier', 
            'products.images'
        ])->find($id);

        if (!$purchase) {
            return response()->json(['message' => 'Compra no encontrada'], 404);
        }

        $colorIds = $purchase->products->pluck('pivot.color_id')->filter()->unique();
        $colors = \App\Models\Color::whereIn('id', $colorIds)->get()->keyBy('id');

        $totalPurchase = 0;

        $items = $purchase->products->map(function ($product) use ($colors, &$totalPurchase) {
            
            $qty      = $product->pivot->quantity;
            $cost     = $product->pivot->cost;
            $discountPercent = $product->pivot->discount ?? 0;
            $colorId  = $product->pivot->color_id;

            $netCost  = $cost * (1 - ($discountPercent / 100));
            $subtotal = $netCost * $qty;
            $totalPurchase += $subtotal;

            $colorData = null;
            if ($colorId && isset($colors[$colorId])) {
                $c = $colors[$colorId];
                $colorData = [
                    'id'   => $c->id,
                    'name' => $c->name,
                    'hex'  => $c->color, 
                ];
            }

            $imgUrl = null;
            if ($product->images->isNotEmpty()) {
                $imgUrl = asset('storage/' . $product->images->first()->url);
            }

            return [
                'product_id' => $product->id,
                'code'       => $product->code,
                'name'       => $product->name,
                'image'      => $imgUrl,
                'quantity'   => (float) $qty,
                'cost'       => (float) $cost,
                'discount'   => (float) $discountPercent,
                'subtotal'   => round($subtotal, 2),
                'color'      => $colorData, 
            ];
        });

        return response()->json([
            'id'            => $purchase->id,
            'code'          => $purchase->code,
            'date'          => $purchase->date->format('Y-m-d'),
            'notes'         => $purchase->notes,
            'created_at'    => $purchase->created_at,
            'exchange_rate' => (float) $purchase->exchange_rate, // Exponemos la tasa
            'total'         => round($totalPurchase, 2), // Total USD
            'total_ves'     => round($totalPurchase * $purchase->exchange_rate, 2), // Total VES calculado con la tasa guardada
            'document_url'  => $purchase->document_url,
            'supplier'      => [
                'id'    => $purchase->supplier->id,
                'name'  => $purchase->supplier->name,
                'rif'   => $purchase->supplier->rif,
                'email' => $purchase->supplier->email,
            ],
            'items'         => $items
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $purchase = Purchase::find($id);

        if (!$purchase) {
            return response()->json(['message' => 'Compra no encontrada'], 404);
        }

        $validator = Validator::make($request->all(), [
            'supplier_id'         => 'required|exists:suppliers,id',
            'code'                => 'required|string|unique:purchases,code,' . $purchase->id,
            'date'                => 'required|date',
            'notes'               => 'nullable|string',
            'products'            => 'required|array|min:1',
            'products.*.id'       => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.cost'     => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.color_id' => 'nullable|exists:colors,id',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // --- 1. RECALCULAR TASA POR SI CAMBIARON LA FECHA ---
        $vesCurrency = Currency::where('code', 'VES')->first();
        $exchangeRate = $purchase->exchange_rate; // Mantenemos la actual por defecto

        if ($vesCurrency) {
            $rateRecord = CurrencyExchange::where('currency_id', $vesCurrency->id)
                ->where('valid_at', '<=', Carbon::parse($request->date)->endOfDay())
                ->orderBy('valid_at', 'desc')
                ->first();
            
            // Actualizamos la tasa basada en la nueva fecha proporcionada
            $exchangeRate = $rateRecord ? $rateRecord->rate : $exchangeRate;
        }
        // -----------------------------------------------------

        $documentPath = $purchase->document;

        if ($request->hasFile('document')) {
            // Si hay un archivo viejo, lo borramos físicamente del servidor
            if ($purchase->document && Storage::disk('public')->exists($purchase->document)) {
                Storage::disk('public')->delete($purchase->document);
            }
            // Guardamos el nuevo
            $documentPath = $request->file('document')->store('purchases_docs', 'public');
        }

        DB::beginTransaction();

        try {
            // 2. ACTUALIZAR CABECERA
            $purchase->update([
                'supplier_id'   => $request->supplier_id,
                'code'          => $request->code,
                'date'          => $request->date,
                'notes'         => $request->notes,
                'exchange_rate' => $exchangeRate,
                'document'      => $documentPath
            ]);

            // 3. LIMPIEZA TOTAL DE ESTA COMPRA (Reset)
            $purchase->movements()->delete();
            $purchase->products()->detach();

            // 4. RE-INSERTAR LOS DATOS NUEVOS
            foreach ($request->products as $item) {
                $prodId   = $item['id'];
                $qty      = $item['quantity'];
                $cost     = $item['cost'];
                $discount = $item['discount'] ?? 0;
                $colorId  = $item['color_id'] ?? null;

                $purchase->products()->attach($prodId, [
                    'quantity' => $qty,
                    'cost'     => $cost,
                    'discount' => $discount,
                    'color_id' => $colorId
                ]);

                ProductMovement::create([
                    'product_id'        => $prodId,
                    'quantity'          => $qty,
                    'color_id'          => $colorId,
                    'movement_date'     => $request->date, 
                    'movementable_id'   => $purchase->id,
                    'movementable_type' => Purchase::class
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Compra actualizada correctamente',
                'data'    => $purchase->load('products')
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al actualizar compra', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $purchase = Purchase::find($id);

        if (!$purchase) {
            return response()->json(['message' => 'Compra no encontrada'], 404);
        }

        DB::beginTransaction();

        try {
            if ($purchase->document && Storage::disk('public')->exists($purchase->document)) {
                Storage::disk('public')->delete($purchase->document);
            }

            $purchase->movements()->delete();
            $purchase->products()->detach();
            $purchase->delete();

            DB::commit();

            return response()->json([
                'message' => 'Compra eliminada correctamente. El stock ha sido revertido.'
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error al eliminar la compra',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}