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
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // El cliente
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete(); // La orden final
            $table->text('description');
            // La Máquina de Estados del Encargo
            $table->enum('status', [
                'created',               // 1. Recién enviado
                'waiting_for_response',  // 2. Staff hizo sugerencia
                'approved',              // 3. Listo para fabricar
                'quoted',                // 4. Se crearon los muebles y la orden
                'paid',                  // 5. Pagado
                'rejected'               // 6. Rechazado/Cancelado
            ])->default('created');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commissions');
    }
};
