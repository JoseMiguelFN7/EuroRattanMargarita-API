<?php

namespace App\Observers;

use App\Models\ProductMovement;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class ProductMovementObserver
{
    /**
     * Handle the ProductMovement "created" event.
     */
    public function created(ProductMovement $movement): void
    {
        // 1. Cargamos el producto, su material, las vistas de stock y el color del movimiento
        $movement->loadMissing(['product.material', 'product.stocks', 'color']);
        
        $product = $movement->product;

        if (!$product || !$product->material) {
            return;
        }

        $material = $product->material;

        if (is_null($material->min_stock) && is_null($material->max_stock)) {
            return;
        }

        // 2. Filtramos la colección de stocks buscando la fila que coincida con el color del movimiento.
        // Como 'color_id' puede ser null (si el producto no usa colores), esta validación funciona perfecto.
        $stockView = $product->stocks->where('colorID', $movement->color_id)->first();

        // Extraemos el valor exacto de la columna 'stock' (o 0 si por alguna razón no existe la fila)
        $currentStock = $stockView ? (float) $stockView->stock : 0;

        // 3. Preparamos el nombre para la notificación (Ej: "Tela Sintética" o "Tela Sintética (Azul Marino)")
        $colorName = $movement->color ? $movement->color->name : ($stockView->color_name ?? null);
        $productDisplayName = $colorName ? "{$product->name} ({$colorName})" : $product->name;

        try {
            if (!is_null($material->min_stock) && $currentStock <= $material->min_stock) {
    
                // Determinamos la frase correcta según el nivel
                $estadoCondicion = $currentStock < $material->min_stock 
                    ? 'ha caído por debajo de' 
                    : 'ha alcanzado';

                NotificationService::notifyByPermission(
                    'products.movements.view', 
                    'Alerta de Stock Crítico',
                    "El material {$productDisplayName} {$estadoCondicion} su nivel mínimo. Stock actual: {$currentStock} (Mínimo: {$material->min_stock}).",
                    'product', 
                    $product->code, 
                    'error' 
                );
            }

            // 5. Validación de Stock Máximo
            if (!is_null($material->max_stock) && $currentStock >= $material->max_stock) {
                NotificationService::notifyByPermission(
                    'products.movements.view',
                    'Alerta de Sobre Inventario',
                    "El material {$productDisplayName} ha superado el nivel máximo permitido. Stock actual: {$currentStock} (Máximo: {$material->max_stock}).",
                    'product',
                    $product->code,
                    'warning' 
                );
            }
        } catch (\Exception $e) {
            Log::error("Error enviando alerta de stock para producto {$product->code} (Color ID: {$movement->color_id}): " . $e->getMessage());
        }
    }

    /**
     * Handle the ProductMovement "updated" event.
     */
    public function updated(ProductMovement $productMovement): void
    {
        //
    }

    /**
     * Handle the ProductMovement "deleted" event.
     */
    public function deleted(ProductMovement $productMovement): void
    {
        //
    }

    /**
     * Handle the ProductMovement "restored" event.
     */
    public function restored(ProductMovement $productMovement): void
    {
        //
    }

    /**
     * Handle the ProductMovement "force deleted" event.
     */
    public function forceDeleted(ProductMovement $productMovement): void
    {
        //
    }
}
