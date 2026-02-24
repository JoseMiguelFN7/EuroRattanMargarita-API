<?php

namespace App\Http\Controllers;

use App\Models\InventoryAdjustment;
use App\Models\ProductMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryAdjustmentController extends Controller
{
    /**
     * Obtener listado de ajustes con paginación
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        // Solo cargamos el usuario para aligerar la carga de la tabla principal
        $query = InventoryAdjustment::with(['user:id,name,email']);

        if ($request->has('search')) {
            $query->where('concept', 'like', '%' . $request->search . '%');
        }

        $adjustments = $query->latest()->paginate($perPage);

        return response()->json($adjustments);
    }

    /**
     * Mostrar un ajuste específico con sus detalles
     */
    public function show($id)
    {
        $adjustment = InventoryAdjustment::with([
            'user:id,name,email',
            'products',      // Trae los productos con los datos de la tabla pivote (color y cantidad)
            'movements'      // Relación polimórfica para ver el registro exacto del kardex
        ])->find($id);

        if (!$adjustment) {
            return response()->json(['message' => 'Ajuste de inventario no encontrado'], 404);
        }

        return response()->json($adjustment);
    }

    /**
     * Crear el ajuste y asentar los movimientos en el inventario
     */
    public function store(Request $request)
    {
        // 1. Validar la petición
        $validator = Validator::make($request->all(), [
            'concept'              => 'required|string|max:255',
            'products'             => 'required|array|min:1',
            'products.*.id'        => 'required|integer|exists:products,id',
            'products.*.color_id'  => 'nullable|integer|exists:colors,id',
            'products.*.quantity'  => 'required|numeric|not_in:0', 
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // 2. Crear la cabecera del ajuste tomando el usuario autenticado por Sanctum
            $adjustment = InventoryAdjustment::create([
                'concept' => $request->concept,
                'user_id' => auth('sanctum')->id(),
            ]);

            $now = now();

            // 3. Iterar sobre los productos para llenar la pivote y crear los movimientos
            foreach ($request->products as $item) {
                
                // A. Insertar en la tabla pivote (inventory_adjustment_product)
                $adjustment->products()->attach($item['id'], [
                    'color_id' => $item['color_id'] ?? null,
                    'quantity' => $item['quantity'],
                ]);

                // B. Registrar el movimiento en el inventario usando la relación polimórfica
                ProductMovement::create([
                    'product_id'        => $item['id'],
                    'color_id'          => $item['color_id'] ?? null,
                    'quantity'          => $item['quantity'],
                    'movement_date'     => $now,
                    // Llenado Polimórfico
                    'movementable_id'   => $adjustment->id,
                    'movementable_type' => InventoryAdjustment::class,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Ajuste de inventario aplicado correctamente.',
                'data'    => $adjustment->load(['user', 'products'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ocurrió un error al procesar el ajuste de inventario.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar el ajuste de inventario (Concepto y Cantidades)
     */
    public function update(Request $request, $id)
    {
        $adjustment = InventoryAdjustment::find($id);

        if (!$adjustment) {
            return response()->json(['message' => 'Ajuste no encontrado'], 404);
        }

        // 1. Validar la petición (misma validación que en el store)
        $validator = Validator::make($request->all(), [
            'concept'              => 'required|string|max:255',
            'products'             => 'required|array|min:1',
            'products.*.id'        => 'required|integer|exists:products,id',
            'products.*.color_id'  => 'nullable|integer|exists:colors,id',
            'products.*.quantity'  => 'required|numeric|not_in:0', 
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // 2. Actualizar la cabecera (Concepto)
            $adjustment->update([
                'concept' => $request->concept
            ]);

            // 3. REVERSIÓN: Eliminar los movimientos de inventario anteriores
            // Al hacer esto, el stock de los productos vuelve a como estaba antes del ajuste
            ProductMovement::where('movementable_type', InventoryAdjustment::class)
                ->where('movementable_id', $adjustment->id)
                ->delete();

            // 4. LIMPIEZA: Vaciar la tabla pivote (inventory_adjustment_product)
            $adjustment->products()->detach();

            $now = now();

            // 5. RECONSTRUCCIÓN: Insertar los nuevos datos tal como vinieron en el request
            foreach ($request->products as $item) {
                
                // A. Insertar en la tabla pivote nuevamente
                $adjustment->products()->attach($item['id'], [
                    'color_id' => $item['color_id'] ?? null,
                    'quantity' => $item['quantity'],
                ]);

                // B. Registrar los nuevos movimientos en el inventario
                ProductMovement::create([
                    'product_id'        => $item['id'],
                    'color_id'          => $item['color_id'] ?? null,
                    'quantity'          => $item['quantity'],
                    'movement_date'     => $now,
                    'movementable_id'   => $adjustment->id,
                    'movementable_type' => InventoryAdjustment::class,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Ajuste de inventario editado y recalculado correctamente.',
                'data'    => $adjustment->load(['user', 'products'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al intentar actualizar el ajuste de inventario.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar el ajuste (Esto REVIERTE las cantidades en el inventario)
     */
    public function destroy($id)
    {
        $adjustment = InventoryAdjustment::find($id);

        if (!$adjustment) {
            return response()->json(['message' => 'Ajuste no encontrado'], 404);
        }

        DB::beginTransaction();

        try {
            // 1. Eliminar los movimientos del inventario asociados a este ajuste.
            // Al borrarlos de ProductMovement, si tu vista/stock lee de ahí, el stock vuelve a la normalidad.
            ProductMovement::where('movementable_type', InventoryAdjustment::class)
                ->where('movementable_id', $adjustment->id)
                ->delete();

            // 2. Limpiar la tabla pivote (Si le pusiste cascadeOnDelete() en la migración, esto es opcional, 
            // pero siempre es buena práctica hacerlo explícito)
            $adjustment->products()->detach();

            // 3. Eliminar el ajuste
            $adjustment->delete();

            DB::commit();

            return response()->json(['message' => 'Ajuste de inventario anulado y existencias revertidas con éxito.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al intentar revertir el ajuste.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}
