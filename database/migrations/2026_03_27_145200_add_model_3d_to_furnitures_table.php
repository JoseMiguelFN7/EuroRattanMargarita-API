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
        Schema::table('furnitures', function (Blueprint $table) {
            // Agregamos la columna para la ruta del modelo 3D (puede ser nula si el mueble no tiene)
            $table->string('model_3d')->nullable()->after('commission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('furnitures', function (Blueprint $table) {
            $table->dropColumn('model_3d');
        });
    }
};
