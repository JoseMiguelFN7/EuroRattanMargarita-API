<?php

namespace App\Observers;

use App\Models\Order;
use App\Jobs\GenerateInvoiceJob;

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
        // 1. isDirty('status') verifica si el campo 'status' fue modificado en esta petición
        // 2. Comprobamos que el nuevo valor sea exactamente 'completed'
        if ($order->isDirty('status') && $order->status === 'completed') {
            
            // 3. Seguro anti-duplicados: Solo despachamos si la orden NO tiene factura aún
            if (!$order->invoice()->exists()) {
                
                // Despachamos el Job a la cola para que se ejecute en segundo plano
                GenerateInvoiceJob::dispatch($order);
                
            }
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
