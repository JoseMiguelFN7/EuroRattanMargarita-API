<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Actualizamos la tabla orders
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 4)->change();
        });

        // Actualizamos la tabla payments
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 4)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertimos a 2 decimales en caso de hacer rollback (ajusta el 10, 2 a lo que tenías originalmente)
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 2)->change();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('exchange_rate', 10, 2)->change();
        });
    }
};
