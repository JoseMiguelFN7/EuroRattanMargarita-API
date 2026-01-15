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
    // 1. Tabla de Permisos (Ej: 'create_product', 'view_reports')
    Schema::create('permissions', function (Blueprint $table) {
        $table->id();
        $table->string('name'); // Nombre legible: "Crear Producto"
        $table->string('slug')->unique(); // Identificador código: "products.create"
        $table->timestamps();
    });

    // 2. Tabla Pivote (Relación Muchos a Muchos: Un Rol tiene muchos Permisos)
    Schema::create('permission_role', function (Blueprint $table) {
        $table->id();
        
        // Claves foráneas
        $table->foreignId('role_id')->constrained()->onDelete('cascade');
        $table->foreignId('permission_id')->constrained()->onDelete('cascade');
        
        // Evitar duplicados (Un rol no puede tener el mismo permiso 2 veces)
        $table->unique(['role_id', 'permission_id']); 
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('permission_role');
    }
};
