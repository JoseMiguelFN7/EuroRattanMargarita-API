<?php

namespace App\Observers;

use App\Models\Order;
use App\Jobs\GenerateInvoiceJob;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Log;
use App\Models\Furniture;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderCancelledMail;

class OrderObserver
{
    /**
     * Handle the Order "created" event.
     */
    public function created(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "updated" event.
     */
    public function updated(Order $order): void
    {
        if (!$order->isDirty('status')) {
            return;
        }

        switch ($order->status) {
            case 'completed':
                if ($order->commission) {
                    $order->commission->update(['status' => 'paid']);
                }

                if (!$order->invoice()->exists()) {
                    GenerateInvoiceJob::dispatch($order);
                }
                break;

            case 'cancelled':
                $inventoryService = app(InventoryService::class);
                $now = now();

                // --- BLOQUE 1: INVENTARIO CRÍTICO ---
                try {
                    $inventoryService->reverseMovements($order);

                    if ($order->commission) {
                        $commission = $order->commission;
                        $inventoryService->reverseMovements($commission);

                        $order->loadMissing('products');
                        Log::info("Cancelación de Encargo: {$commission->code}. Procesando " . $order->products->count() . " productos.");

                        // 3. Movimientos compensatorios
                        foreach ($order->products as $orderProduct) {
                            Log::info("Buscando receta para Mueble. Commission ID: {$commission->id} | Product ID: {$orderProduct->id}");
                            
                            $furniture = Furniture::with('materials')
                                                  ->where('commission_id', $commission->id)
                                                  ->where('product_id', $orderProduct->id)
                                                  ->first();

                            if ($furniture) {
                                $manufacturedQuantity = $orderProduct->pivot->quantity;
                                Log::info("Mueble encontrado exitosamente. Cantidad a revertir: {$manufacturedQuantity}");

                                foreach ($furniture->materials as $material) {
                                    $totalRefund = $material->pivot->quantity * $manufacturedQuantity;

                                    $inventoryService->recordMovement(
                                        $material->product_id,
                                        abs($totalRefund), 
                                        $material->pivot->color_id,
                                        $now,
                                        $order 
                                    );
                                    
                                    Log::info("Material ID {$material->product_id} reintegrado. Cantidad: " . abs($totalRefund));
                                }
                            } else {
                                // AQUI ESTA LA CLAVE PARA DEPURAR:
                                Log::warning("¡ALERTA! No se encontró receta (Furniture). La base de datos no tiene un registro en la tabla 'furnitures' con commission_id = {$commission->id} y product_id = {$orderProduct->id}");
                            }
                        }
                        $commission->update(['status' => 'rejected']);
                    }
                } catch (\Exception $e) {
                    Log::error("Error revirtiendo inventario orden {$order->code}: " . $e->getMessage());
                    throw $e; // Si falla el inventario, SÍ abortamos todo.
                }

                // --- BLOQUE 2: NOTIFICACIONES (NO CRÍTICO) ---
                try {
                    $order->loadMissing('user');
                    if ($order->user) {
                        Mail::to($order->user->email)->queue(new OrderCancelledMail($order));
                        Log::info("Correo de cancelación encolado para el usuario: {$order->user->email}");
                    }
                } catch (\Exception $e) {
                    // Si falla el correo, solo lo registramos, pero NO lanzamos 'throw'.
                    // Así el inventario se guarda exitosamente de todos modos.
                    Log::error("No se pudo enviar el correo de cancelación para {$order->code}: " . $e->getMessage());
                }
                break;
        }
    }

    /**
     * Handle the Order "deleted" event.
     */
    public function deleted(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "restored" event.
     */
    public function restored(Order $order): void
    {
        //
    }

    /**
     * Handle the Order "force deleted" event.
     */
    public function forceDeleted(Order $order): void
    {
        //
    }
}
