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
        // 1. RENOMBRADO INTELIGENTE (Detecta si ya ocurrió el error)
        // Solo intentamos renombrar si la tabla vieja todavía existe.
        if (Schema::hasTable('receipts')) {
            Schema::rename('receipts', 'orders');
        }

        if (Schema::hasTable('receipts_products')) {
            Schema::rename('receipts_products', 'order_items');
        }

        // 2. MODIFICAR LA TABLA (Asumiendo que ya se llama order_items)
        Schema::table('order_items', function (Blueprint $table) {
            
            // A. BORRAR LA FK ZOMBIE
            // Aquí está el truco: Especificamos el nombre LITERAL de la llave antigua.
            // Usamos un array con el nombre viejo para forzar a Laravel a buscar esa string exacta.
            // Si te da error de que no existe, comenta esta línea, pero es necesaria en el 99% de los casos.
            $table->dropForeign('receipts_products_receipt_id_foreign');
            
            // B. Renombrar la columna
            // Verificamos si la columna ya se llama order_id para no dar error si corres esto dos veces
            if (Schema::hasColumn('order_items', 'receipt_id')) {
                $table->renameColumn('receipt_id', 'order_id');
            }

            // C. Crear la nueva FK apuntando a 'orders'
            // Solo la agregamos si no existe (Laravel no tiene hasForeignKey fácil, así que confiamos en el drop previo)
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');

            // D. Columnas nuevas
            // Verificamos una para saber si agregamos el bloque
            if (!Schema::hasColumn('order_items', 'variant_id')) {
                $table->foreignId('variant_id')->nullable()->after('product_id')->constrained('colors'); 
                $table->string('product_name')->nullable()->after('product_id'); 
                $table->boolean('is_natural')->default(true)->after('variant_id');
                $table->decimal('subtotal', 10, 2)->default(0)->after('price'); 
            }
        });

        // 3. ACTUALIZAR LA TABLA ORDERS
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'status')) {
                $table->enum('status', ['pending_payment', 'verifying_payment', 'processing', 'completed', 'cancelled'])
                      ->default('pending_payment')
                      ->after('id');
                
                $table->decimal('total_amount_usd', 12, 2)->default(0);
                $table->decimal('exchange_rate', 12, 2)->nullable();
                $table->text('notes')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // REVERTIR CAMBIOS
        
        // 1. Quitar columnas nuevas de orders
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['status', 'exchange_rate', 'notes', 'total_amount_usd']);
        });

        // 2. Quitar FK y columnas de items
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['order_id']); // Laravel buscará 'order_items_order_id_foreign'
            $table->dropColumn(['variant_id']); // Y product_name si lo agregaste
            $table->renameColumn('order_id', 'receipt_id');
        });

        // 3. Renombrar tablas de vuelta
        Schema::rename('orders', 'receipts');
        Schema::rename('order_items', 'receipts_products');

        // 4. Restaurar la FK original
        Schema::table('receipts_products', function (Blueprint $table) {
             $table->foreign('receipt_id')->references('id')->on('receipts')->onDelete('cascade');
        });
    }
};
