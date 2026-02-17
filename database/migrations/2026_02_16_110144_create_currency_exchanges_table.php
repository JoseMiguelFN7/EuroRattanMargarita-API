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
        Schema::create('currency_exchanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->onDelete('cascade');
    
            // La tasa de cambio respecto a la moneda primaria (USD)
            // Usamos decimal con alta precisión para evitar errores de redondeo
            $table->decimal('rate', 20, 8); 
            
            // Fecha de validez de la tasa (o created_at)
            $table->timestamp('valid_at')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_exchanges');
    }
};
