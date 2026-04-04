<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL; // <--- AGREGADO
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
        // --- FORZAR HTTPS EN PRODUCCIÓN ---
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // --- BLOQUE DE PERMISOS (CONSTRUIDO PARA SER TOLERANTE A FALLOS) ---
        try {
            if (!app()->runningInConsole()) {
                if (Schema::hasTable('permissions')) {
                    $permissions = Permission::all();
                    foreach ($permissions as $permission) {
                        Gate::define($permission->slug, function ($user) use ($permission) {
                            return $user->hasPermission($permission->slug);
                        });
                    }
                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Base de datos no lista aún. Saltando carga de permisos.");
        }

        // --- REGISTRO DE OBSERVERS ---
        Order::observe(OrderObserver::class);
        Commission::observe(CommissionObserver::class);
        CommissionSuggestion::observe(SuggestionObserver::class);
        Payment::observe(PaymentObserver::class);
        ProductMovement::observe(ProductMovementObserver::class);
    }
}