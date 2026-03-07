<?php

namespace App\Observers;

use App\Models\Commission;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use App\Mail\CommissionQuotedMail;
use App\Mail\CommissionRejectedMail;
use Illuminate\Support\Facades\Log;

class CommissionObserver
{
    /**
     * Handle the Commission "created" event.
     */
    public function created(Commission $commission): void
    {
        $commission->loadMissing('user');
        $customerName = $commission->user ? $commission->user->name : 'Un cliente';

        try {
            NotificationService::notifyByPermission(
                'commissions.view',
                'Nuevo Encargo Solicitado',
                "{$customerName} ha creado una nueva solicitud de encargo (#{$commission->code}).",
                'commission',
                $commission->code,
                'info'
            );
        } catch (\Exception $e) {
            Log::error("Error enviando notificación de encargo creado {$commission->code}: " . $e->getMessage());
        }
    }

    /**
     * Handle the Commission "updated" event.
     */
    public function updated(Commission $commission): void
    {
        // Solo nos interesa si el estado cambió
        if (!$commission->isDirty('status')) {
            return;
        }

        // Cargamos el usuario para tener su correo
        $commission->loadMissing('user');
        
        if (!$commission->user) {
            return;
        }

        switch ($commission->status) {
            case 'quoted':
                // Se generó la cotización y está lista para revisión del cliente
                try {
                    Mail::to($commission->user->email)->queue(new CommissionQuotedMail($commission));
                    Log::info("Correo de cotización encolado para el usuario: {$commission->user->email}");
                } catch (\Exception $e) {
                    Log::error("Error enviando correo de cotización para el encargo {$commission->code}: " . $e->getMessage());
                }
                break;

            case 'rejected':
                // Se rechazó, PERO validamos que no tenga orden asociada
                // (Si tiene orden, significa que se canceló la orden, y eso ya manda su propio correo)
                $commission->loadMissing('order');
                
                if (!$commission->order) {
                    try {
                        Mail::to($commission->user->email)->queue(new CommissionRejectedMail($commission));
                        Log::info("Correo de rechazo de encargo encolado para el usuario: {$commission->user->email}");

                        if (auth('sanctum')->check() && auth('sanctum')->id() === $commission->user_id) {
                        try {
                            NotificationService::notifyByPermission(
                                'commissions.view',
                                'Encargo Cancelado por el Cliente',
                                "El cliente {$commission->user->name} ha cancelado su encargo #{$commission->code}.",
                                'commission',
                                $commission->code,
                                'warning' // Amarillo/Alerta, ya que se perdió una posible venta
                            );
                        } catch (\Exception $e) {
                            Log::error("Error notificación rechazo encargo {$commission->code}: " . $e->getMessage());
                        }
                    }
                    } catch (\Exception $e) {
                        Log::error("Error enviando correo de rechazo para el encargo {$commission->code}: " . $e->getMessage());
                    }
                }
                break;
        }
    }

    /**
     * Handle the Commission "deleted" event.
     */
    public function deleted(Commission $commission): void
    {
        //
    }

    /**
     * Handle the Commission "restored" event.
     */
    public function restored(Commission $commission): void
    {
        //
    }

    /**
     * Handle the Commission "force deleted" event.
     */
    public function forceDeleted(Commission $commission): void
    {
        //
    }
}
