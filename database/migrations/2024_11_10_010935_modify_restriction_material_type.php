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
        Schema::table('material_types_materials', function (Blueprint $table) {
            // Primero, eliminar las restricciones existentes
            $table->dropForeign(['material_id']);
            $table->dropForeign(['material_type_id']);

            // Luego, volver a añadir las claves foráneas con onDelete('cascade')
            $table->foreign('material_id')
                ->references('id')->on('materials')
                ->onDelete('cascade')
                ->onUpdate('cascade');;

            $table->foreign('material_type_id')
                ->references('id')->on('material_types')
                ->onDelete('cascade')
                ->onUpdate('cascade');;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('material_types_materials', function (Blueprint $table) {
            // Eliminar las restricciones que hemos agregado en el método up
            $table->dropForeign(['material_id']);
            $table->dropForeign(['material_type_id']);

            // Agregar de nuevo las restricciones sin el delete cascade si era el caso
            $table->foreign('material_id')
                ->references('id')->on('materials')
                ->onDelete('restrict')
                ->onUpdate('cascade');

            $table->foreign('material_type_id')
                ->references('id')->on('material_types')
                ->onDelete('restrict')
                ->onUpdate('cascade');
        });
    }
};
