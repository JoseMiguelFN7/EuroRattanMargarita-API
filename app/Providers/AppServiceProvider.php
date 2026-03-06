<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use App\Models\Permission;
use App\Models\Order;
use App\Models\Commission;
use App\Models\CommissionSuggestion;
use App\Observers\OrderObserver;
use App\Observers\CommissionObserver;
use App\Observers\SuggestionObserver;

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
        if (Schema::hasTable('permissions'))
        {
            $permissions = Permission::all();

            foreach ($permissions as $permission) {
                Gate::define($permission->slug, function ($user) use ($permission) {
                    return $user->hasPermission($permission->slug);
                });
            }
        }

        Order::observe(OrderObserver::class);
        Commission::observe(CommissionObserver::class);
        CommissionSuggestion::observe(SuggestionObserver::class);
    }
}
