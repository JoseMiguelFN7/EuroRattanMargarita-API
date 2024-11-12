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
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->decimal('cost', 10, 2);
            $table->timestamps();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('material_types_id');
            $table->unsignedBigInteger('unit_id');

            $table->foreign('product_id')
            ->references('id')->on('products')
            ->onDelete('restrict')
            ->onUpdate('cascade');

            $table->foreign('material_types_id')
            ->references('id')->on('material_types')
            ->onDelete('restrict')
            ->onUpdate('cascade');

            $table->foreign('unit_id')
            ->references('id')->on('units')
            ->onDelete('restrict')
            ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
