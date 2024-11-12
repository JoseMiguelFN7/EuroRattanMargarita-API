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
        Schema::create('furnitures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('furniture_types_id');
            $table->decimal('profit', 10, 2);
            $table->decimal('paint', 10, 2);
            $table->decimal('labor', 10, 2);
            $table->timestamps();

            $table->foreign('product_id')
            ->references('id')->on('products')
            ->onDelete('restrict')
            ->onUpdate('cascade');

            $table->foreign('furniture_types_id')
            ->references('id')->on('furnitures_types')
            ->onDelete('restrict')
            ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('furnitures');
    }
};
