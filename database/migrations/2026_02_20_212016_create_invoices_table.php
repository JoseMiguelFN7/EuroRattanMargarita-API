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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            // Relación con la orden
            $table->foreignId('order_id')
                  ->constrained('orders')
                  ->restrictOnDelete();

            // Numeración y Control
            $table->string('invoice_number')->unique();
            $table->string('control_number')->unique();

            // Datos del Adquiriente
            $table->string('client_name');
            $table->string('client_document');
            $table->text('client_address')->nullable();

            // Montos (Margarita / Puerto Libre por defecto)
            $table->decimal('exempt_amount', 15, 2)->default(0); 
            $table->decimal('tax_base_amount', 15, 2)->default(0); 
            $table->decimal('tax_percentage', 5, 2)->default(0.00); 
            $table->decimal('tax_amount', 15, 2)->default(0); 
            $table->decimal('total_amount', 15, 2); 

            // Archivo y Fechas
            $table->string('pdf_url')->nullable(); 
            $table->timestamp('emitted_at')->useCurrent();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
