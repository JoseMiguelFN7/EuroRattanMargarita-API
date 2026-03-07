<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewPaymentReportedMail;
use Illuminate\Support\Facades\Log;

class PaymentObserver
{
    /**
     * Handle the Payment "created" event.
     */
    public function created(Payment $payment): void
    {
        // Cargamos la orden y al usuario dueño de esa orden para armar el mensaje
        $payment->loadMissing(['order.user']);
        
        $orderCode = $payment->order ? $payment->order->code : 'Desconocida';
        $customerName = ($payment->order && $payment->order->user) ? $payment->order->user->name : 'Un cliente';

        try {
            NotificationService::notifyByPermission(
                'payments.verify', // <-- El slug del permiso encargado de conciliar cuentas
                'Pago Pendiente de Verificación',
                "{$customerName} ha reportado un nuevo pago para la orden #{$orderCode}.",
                'order', // <-- Enviamos 'order' para que el frontend abra la vista de la orden
                $orderCode, // <-- Pasamos el código de la orden
                'warning' // 'warning' (amarillo) es ideal porque es una acción que requiere revisión humana
            );
        } catch (\Exception $e) {
            Log::error("Error enviando notificación del pago {$payment->id}: " . $e->getMessage());
        }

        // Enviar correo al staff
        try {
            // Usamos nuestro Scope para traer solo a los usuarios con ese permiso
            $staffUsers = User::withPermission('payments.verify')->get();
            
            if ($staffUsers->isNotEmpty()) {
                Mail::to($staffUsers)->queue(new NewPaymentReportedMail($payment));
            }
        } catch (\Exception $e) {
            Log::error("Error enviando correo al staff por el pago {$payment->id}: " . $e->getMessage());
        }
    }

    /**
     * Handle the Payment "updated" event.
     */
    public function updated(Payment $payment): void
    {
        //
    }

    /**
     * Handle the Payment "deleted" event.
     */
    public function deleted(Payment $payment): void
    {
        //
    }

    /**
     * Handle the Payment "restored" event.
     */
    public function restored(Payment $payment): void
    {
        //
    }

    /**
     * Handle the Payment "force deleted" event.
     */
    public function forceDeleted(Payment $payment): void
    {
        //
    }
}
