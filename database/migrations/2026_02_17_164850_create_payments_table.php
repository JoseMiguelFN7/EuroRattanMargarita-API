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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            
            // 1. Relaciones
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('currency_id')->constrained(); // La moneda en la que pagó (ej: Bs)
            
            // Recomendado: Saber a qué banco/método entró el dinero
            $table->foreignId('payment_method_id')->constrained(); 

            // 2. Datos del Pago
            $table->decimal('amount', 12, 2); // Monto pagado
            
            $table->string('reference_number')->nullable(); // Ej: 123456 (Nullable por si es efectivo)
            $table->string('proof_image')->nullable(); // Ruta del archivo
            
            // 3. Estado y Auditoría
            $table->enum('status', ['pending', 'verified', 'rejected'])->default('pending');
            $table->timestamp('verified_at')->nullable(); // Cuándo se aprobó
            
            // Opcional: Tasa de cambio al momento del pago (Snapshot)
            $table->decimal('exchange_rate', 12, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
