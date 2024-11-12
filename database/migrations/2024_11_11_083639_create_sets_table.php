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
        Schema::create('sets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('set_types_id');
            $table->decimal('profit_per', 10, 2);
            $table->decimal('paint_per', 10, 2);
            $table->decimal('labor_fab_per', 10, 2);
            $table->timestamps();

            $table->foreign('product_id')
            ->references('id')->on('products')
            ->onDelete('restrict')
            ->onUpdate('cascade');

            $table->foreign('set_types_id')
            ->references('id')->on('set_types')
            ->onDelete('restrict')
            ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sets');
    }
};
