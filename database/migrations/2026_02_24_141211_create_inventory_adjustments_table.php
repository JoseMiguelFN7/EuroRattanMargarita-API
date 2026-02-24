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
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('concept');
            
            $table->foreignId('user_id')->constrained(); 
            
            $table->timestamps();
        });

        Schema::create('inventory_adjustment_product', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('inventory_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            
            $table->foreignId('color_id')->nullable()->constrained()->nullOnDelete();
            
            $table->decimal('quantity', 10, 2); 
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_product');
        Schema::dropIfExists('inventory_adjustments');
    }
};
