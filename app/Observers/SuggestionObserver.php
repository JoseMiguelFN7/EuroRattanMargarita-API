<?php

namespace App\Observers;

use App\Models\CommissionSuggestion;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewSuggestionMail;
use Illuminate\Support\Facades\Log;

class SuggestionObserver
{
    /**
     * Handle the Suggestion "created" event.
     */
    public function created(CommissionSuggestion $suggestion): void
    {
        $suggestion->loadMissing('commission.user');
        $commission = $suggestion->commission;

        if (!$commission || !$commission->user) {
            return;
        }

        if ($suggestion->is_staff) {
            // FLUJO A: Es del Staff -> Enviamos correo al cliente
            try {
                Mail::to($commission->user->email)->queue(new NewSuggestionMail($suggestion, $commission));
            } catch (\Exception $e) {
                Log::error("Error correo sugerencia {$commission->code}: " . $e->getMessage());
            }
        } else {
            // PUNTO 2 (FLUJO B): Es del Cliente -> Enviamos notificación interna al panel administrativo
            try {
                NotificationService::notifyByPermission(
                    'commissions.suggestions.create', // Permiso específico solicitado
                    'Nueva Respuesta del Cliente',
                    "{$commission->user->name} ha respondido en el hilo del encargo #{$commission->code}.",
                    'commission',
                    $commission->code,
                    'info'
                );
            } catch (\Exception $e) {
                Log::error("Error notificación sugerencia {$commission->code}: " . $e->getMessage());
            }
        }
    }

    /**
     * Handle the Suggestion "updated" event.
     */
    public function updated(CommissionSuggestion $suggestion): void
    {
        //
    }

    /**
     * Handle the Suggestion "deleted" event.
     */
    public function deleted(CommissionSuggestion $suggestion): void
    {
        //
    }

    /**
     * Handle the Suggestion "restored" event.
     */
    public function restored(CommissionSuggestion $suggestion): void
    {
        //
    }

    /**
     * Handle the Suggestion "force deleted" event.
     */
    public function forceDeleted(CommissionSuggestion $suggestion): void
    {
        //
    }
}
