<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductMovement;
use App\Models\ProductStockView;
use Illuminate\Database\Eloquent\Model;
use Exception;

class InventoryService
{
    /**
     * Registra un nuevo movimiento de inventario aplicando bloqueo pesimista.
     *
     * @param int $productId
     * @param float $quantity (Positivo para entradas, negativo para salidas)
     * @param int|null $colorId
     * @param string $movementDate
     * @param Model $movementable (El modelo que origina el movimiento: Order, Purchase, etc.)
     * @return ProductMovement
     * @throws Exception
     */
    public function recordMovement($productId, $quantity, $colorId, $movementDate, Model $movementable)
    {
        // 1. Aplicamos el bloqueo pesimista a la fila del producto principal.
        // Nadie más podrá leer o modificar este producto hasta que la transacción actual termine.
        $product = Product::lockForUpdate()->find($productId);

        if (!$product) {
            throw new Exception("El producto con ID {$productId} no existe.");
        }

        // 2. Si es una salida de inventario (cantidad negativa), validamos el stock
        if ($quantity < 0) {
            $stockRecord = ProductStockView::where('productID', $productId)
                ->when($colorId, function ($query) use ($colorId) {
                    return $query->where('colorID', $colorId);
                }, function ($query) {
                    return $query->whereNull('colorID');
                })
                ->first();

            $currentStock = $stockRecord ? (float) $stockRecord->stock : 0;
            $quantityToDeduct = abs($quantity);

            if ($currentStock < $quantityToDeduct) {
                $colorName = $colorId ? " con color ID {$colorId}" : "";
                throw new Exception("Stock insuficiente para el producto '{$product->name}'{$colorName}. Requerido: {$quantityToDeduct}, Disponible: {$currentStock}.");
            }
        }

        // 3. Si pasó la validación (o es una entrada), registramos el movimiento
        return ProductMovement::create([
            'product_id'        => $productId,
            'quantity'          => $quantity,
            'color_id'          => $colorId,
            'movement_date'     => $movementDate,
            'movementable_id'   => $movementable->id,
            'movementable_type' => get_class($movementable),
        ]);
    }

    /**
     * Revierte de forma segura los movimientos asociados a un modelo.
     * Útil al anular órdenes, compras o ajustes.
     *
     * @param Model $movementable
     * @throws Exception
     */
    public function reverseMovements(Model $movementable)
    {
        // 1. Buscamos todos los movimientos que generó esta entidad
        $movements = ProductMovement::where('movementable_type', get_class($movementable))
            ->where('movementable_id', $movementable->id)
            ->get();

        foreach ($movements as $movement) {
            // 2. Bloqueamos el producto antes de hacer cualquier cálculo
            $product = Product::lockForUpdate()->find($movement->product_id);

            // 3. El peligro: Si vamos a revertir una ENTRADA (ej. anulando una compra), 
            // el movimiento original era positivo (> 0). Revertirlo significa RESTAR del inventario actual.
            if ($movement->quantity > 0) {
                $stockRecord = ProductStockView::where('productID', $movement->product_id)
                    ->when($movement->color_id, function ($query) use ($movement) {
                        return $query->where('colorID', $movement->color_id);
                    }, function ($query) {
                        return $query->whereNull('colorID');
                    })
                    ->first();

                $currentStock = $stockRecord ? (float) $stockRecord->stock : 0;

                // Si el stock actual es menor a lo que intento revertir, significa que 
                // ya vendieron o usaron la mercancía de esa compra/ajuste.
                if ($currentStock < $movement->quantity) {
                    throw new Exception("No se puede revertir esta operación. El producto '{$product->name}' ya ha sido utilizado en otras salidas y dejaría el inventario en negativo.");
                }
            }

            // 4. Si todo es seguro, eliminamos el movimiento para revertir su efecto en la vista de stock
            $movement->delete();
        }
    }
}