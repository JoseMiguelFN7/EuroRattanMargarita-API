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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict'); // Si borran al proveedor, que no deje huerfana la compra
            $table->string('code')->unique();
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_product', function (Blueprint $table) {
        $table->id();
        $table->foreignId('purchase_id')->constrained()->onDelete('cascade');
        $table->foreignId('product_id')->constrained()->onDelete('restrict');
        
        // DATOS CRÍTICOS DEL PIVOTE
        $table->foreignId('color_id')->nullable()->constrained('colors')->onDelete('restrict');
        $table->decimal('quantity', 10, 2);
        $table->decimal('cost', 10, 2);
        $table->decimal('discount', 5, 2)->default(0);
        
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_product'); // Borrar primero la hija
        Schema::dropIfExists('purchases');        // Borrar luego la padre
    }
};
