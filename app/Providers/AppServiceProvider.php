<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission;
use App\Models\Order;
use App\Models\Commission;
use App\Models\CommissionSuggestion;
use App\Models\Payment;
use App\Models\ProductMovement;
use App\Observers\OrderObserver;
use App\Observers\CommissionObserver;
use App\Observers\SuggestionObserver;
use App\Observers\PaymentObserver;
use App\Observers\ProductMovementObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // --- BLOQUE DE PERMISOS (CONSTRUIDO PARA SER TOLERANTE A FALLOS) ---
        try {
            if (!app()->runningInConsole()) {
                if (\Illuminate\Support\Facades\Schema::hasTable('permissions')) {
                    $permissions = \App\Models\Permission::all();
                    foreach ($permissions as $permission) {
                        \Illuminate\Support\Facades\Gate::define($permission->slug, function ($user) use ($permission) {
                            return $user->hasPermission($permission->slug);
                        });
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Base de datos no lista aún. Saltando carga de permisos.");
        }

        // --- REGISTRO DE OBSERVERS (ESTOS NO NECESITAN DB ACTIVA PARA REGISTRARSE) ---
        \App\Models\Order::observe(\App\Observers\OrderObserver::class);
        \App\Models\Commission::observe(\App\Observers\CommissionObserver::class);
        \App\Models\CommissionSuggestion::observe(\App\Observers\SuggestionObserver::class);
        \App\Models\Payment::observe(\App\Observers\PaymentObserver::class);
        \App\Models\ProductMovement::observe(\App\Observers\ProductMovementObserver::class);
    }
}
