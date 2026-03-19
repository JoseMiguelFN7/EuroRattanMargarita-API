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
        Schema::create('material_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Ej: Insumo, Tapicería
            $table->timestamps();
        });

        Schema::table('material_types', function (Blueprint $table) {
            $table->foreignId('material_category_id')
                  ->nullable()
                  ->constrained('material_categories')
                  ->onDelete('restrict');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->foreignId('material_type_id')
                  ->nullable()
                  ->constrained('material_types')
                  ->onDelete('restrict');
        });

        Schema::dropIfExists('material_types_materials');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir: Volvemos a crear la tabla pivote
        Schema::create('material_types_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained()->onDelete('cascade');
            $table->foreignId('material_type_id')->constrained('material_types')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropForeign(['material_type_id']);
            $table->dropColumn('material_type_id');
        });

        Schema::table('material_types', function (Blueprint $table) {
            $table->dropForeign(['material_category_id']);
            $table->dropColumn('material_category_id');
        });

        Schema::dropIfExists('material_categories');
    }
};
