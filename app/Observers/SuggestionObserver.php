<?php

namespace App\Observers;

use App\Models\CommissionSuggestion;
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
        if (!$suggestion->is_staff) {
            return;
        }

        // Cargamos el encargo y el usuario dueño del encargo
        $suggestion->loadMissing('commission.user');
        
        $commission = $suggestion->commission;

        if ($commission && $commission->user) {
            try {
                // Le pasamos tanto la sugerencia como el encargo al Mailable
                Mail::to($commission->user->email)->queue(new NewSuggestionMail($suggestion, $commission));
                Log::info("Correo de nueva sugerencia encolado para el usuario: {$commission->user->email}");
            } catch (\Exception $e) {
                Log::error("Error enviando correo de sugerencia para el encargo {$commission->code}: " . $e->getMessage());
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
