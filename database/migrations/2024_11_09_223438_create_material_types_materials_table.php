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
        Schema::create('material_types_materials', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('material_id');
            $table->unsignedBigInteger('material_type_id');

            $table->foreign('material_id')
            ->references('id')->on('materials')
            ->onDelete('restrict')
            ->onUpdate('cascade');

            $table->foreign('material_type_id')
            ->references('id')->on('material_types')
            ->onDelete('restrict')
            ->onUpdate('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('material_types_materials');
    }
};
