<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\ProductMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        
        $purchases = Purchase::with(['supplier', 'products']) // Carga necesaria para el cálculo
                             ->orderBy('date', 'desc')
                             ->paginate($perPage);

        $purchases->getCollection()->each(function ($purchase) {
            $purchase->append('total');
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
            'supplier_id' => 'required|exists:suppliers,id',
            'code'        => 'required|string|unique:purchases,code',
            'date'        => 'required|date',
            'notes'       => 'nullable|string',
            'products'    => 'required|array|min:1',
            'products.*.id'       => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.cost'     => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.color_id' => 'nullable|exists:colors,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // 2. CREAR CABECERA
            $purchase = Purchase::create([
                'supplier_id' => $request->supplier_id,
                'code'        => $request->code,
                'date'        => $request->date,
                'notes'       => $request->notes
            ]);

            foreach ($request->products as $item) {
                $prodId   = $item['id'];
                $qty      = $item['quantity'];
                $cost     = $item['cost'];
                $discount = $item['discount'] ?? 0;
                $colorId  = $item['color_id'] ?? null; // Recibimos el ID

                // 3. GUARDAR PIVOTE (Histórico Financiero)
                $purchase->products()->attach($prodId, [
                    'quantity' => $qty,
                    'cost'     => $cost,
                    'discount' => $discount,
                    'color_id' => $colorId
                ]);

                // 4. GUARDAR MOVIMIENTO
                ProductMovement::create([
                    'product_id' => $prodId,
                    'quantity'   => $qty,
                    'color_id'   => $colorId,
                    'movement_date' => $request->date,
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
        // 1. CARGA DE RELACIONES
        // Cargamos proveedor y productos con sus imágenes
        $purchase = Purchase::with([
            'supplier', 
            'products.images'
        ])->find($id);

        if (!$purchase) {
            return response()->json(['message' => 'Compra no encontrada'], 404);
        }

        // 2. OBTENER COLORES (Optimización)
        // Recolectamos todos los IDs de colores usados en los pivotes para hacer 1 sola consulta
        $colorIds = $purchase->products->pluck('pivot.color_id')->filter()->unique();
        $colors = \App\Models\Color::whereIn('id', $colorIds)->get()->keyBy('id');

        // 3. TRANSFORMACIÓN DE PRODUCTOS (ITEMS)
        $totalPurchase = 0;

        // Mapeamos 'products' a una estructura 'items' más plana para la tabla del front
        $items = $purchase->products->map(function ($product) use ($colors, &$totalPurchase) {
            
            // Datos del Pivote
            $qty      = $product->pivot->quantity;
            $cost     = $product->pivot->cost;
            $discount = $product->pivot->discount;
            $colorId  = $product->pivot->color_id;

            // Cálculos matemáticos
            $netCost  = $cost - $discount; 
            $subtotal = $netCost * $qty;
            $totalPurchase += $subtotal;

            // Resolver Objeto Color
            $colorData = null;
            if ($colorId && isset($colors[$colorId])) {
                $c = $colors[$colorId];
                $colorData = [
                    'id'   => $c->id,
                    'name' => $c->name,
                    'hex'  => $c->color, // Asumo que tu columna hex se llama 'color'
                ];
            }

            // Resolver Imagen Principal
            $imgUrl = null;
            if ($product->images->isNotEmpty()) {
                $imgUrl = asset('storage/' . $product->images->first()->url);
            }

            return [
                'product_id' => $product->id,
                'code'       => $product->code,
                'name'       => $product->name,
                'image'      => $imgUrl,
                // Datos de la transacción
                'quantity'   => (float) $qty,
                'cost'       => (float) $cost,
                'discount'   => (float) $discount,
                'subtotal'   => round($subtotal, 2),
                'color'      => $colorData, // Objeto {id, name, hex} o null
            ];
        });

        // 4. CONSTRUCCIÓN DE RESPUESTA
        return response()->json([
            'id'          => $purchase->id,
            'code'        => $purchase->code,
            'date'        => $purchase->date->format('Y-m-d'), // Formato limpio
            'notes'       => $purchase->notes,
            'created_at'  => $purchase->created_at,
            // Total Calculado
            'total'       => round($totalPurchase, 2),
            // Proveedor Limpio
            'supplier'    => [
                'id'    => $purchase->supplier->id,
                'name'  => $purchase->supplier->name,
                'rif'   => $purchase->supplier->rif,
                'email' => $purchase->supplier->email,
            ],
            // Items transformados
            'items'       => $items
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

        // 1. VALIDACIÓN COMPLETA
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'required|exists:suppliers,id',
            // Validar unique ignorando el ID actual de la compra
            'code'        => 'required|string|unique:purchases,code,' . $purchase->id,
            'date'        => 'required|date',
            'notes'       => 'nullable|string',
            
            // Validamos los productos igual que en el Store
            'products'            => 'required|array|min:1',
            'products.*.id'       => 'required|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:0.01',
            'products.*.cost'     => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.color_id' => 'nullable|exists:colors,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // 2. ACTUALIZAR CABECERA
            $purchase->update([
                'supplier_id' => $request->supplier_id,
                'code'        => $request->code,
                'date'        => $request->date,
                'notes'       => $request->notes
            ]);

            // 3. LIMPIEZA TOTAL DE ESTA COMPRA (Reset)
            // A. Borramos los movimientos asociados (El Stock "bajará" automáticamente al borrar esto)
            $purchase->movements()->delete();
            
            // B. Desvinculamos los productos del pivote (Financiero)
            $purchase->products()->detach();


            // 4. RE-INSERTAR LOS DATOS NUEVOS
            foreach ($request->products as $item) {
                $prodId   = $item['id'];
                $qty      = $item['quantity'];
                $cost     = $item['cost'];
                $discount = $item['discount'] ?? 0;
                $colorId  = $item['color_id'] ?? null;

                // A. Guardar Pivote Nuevo (Financiero)
                $purchase->products()->attach($prodId, [
                    'quantity' => $qty,
                    'cost'     => $cost,
                    'discount' => $discount,
                    'color_id' => $colorId
                ]);

                // B. Crear Movimiento Nuevo (Físico)
                // Usamos la nueva fecha y cantidades
                ProductMovement::create([
                    'product_id' => $prodId,
                    'quantity'   => $qty,
                    'color_id'   => $colorId,
                    'movement_date' => $request->date, // Importante: Usar la fecha de la compra
                    'type'       => 'purchase', // Opcional si usas type
                    
                    // Polimorfismo
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
            // 1. Eliminar Movimientos (Kardex Físico)
            // Esto es lo que hace que el stock "baje" automáticamente en tu vista.
            // Al ser una relación polimórfica, borrará todos los ProductMovement vinculados a esta compra.
            $purchase->movements()->delete();

            // 2. Eliminar Pivotes (Detalle Financiero)
            // Borra la relación en la tabla 'purchase_product'
            $purchase->products()->detach();

            // 3. Eliminar la Compra (Cabecera)
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
