<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Corregir tabla Products (Se mantiene igual, es segura)
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'discount')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('discount', 8, 2)->default(0)->after('sell');
            });
        }

        // 2. Corregir tabla Materials
        if (Schema::hasTable('materials')) {
            Schema::table('materials', function (Blueprint $table) {
                // Renombrar cost a price si existe
                if (Schema::hasColumn('materials', 'cost')) {
                    $table->renameColumn('cost', 'price');
                }
                
                // --- AQUÍ ESTÁ EL CAMBIO ---
                if (Schema::hasColumn('materials', 'material_types_id')) {
                    // Primero eliminamos la relación (Constraint)
                    // Laravel por convención le pone este nombre:
                    $table->dropForeign(['material_types_id']); 
                    
                    // Ahora que no tiene ataduras, la borramos
                    $table->dropColumn('material_types_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('discount');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->renameColumn('price', 'cost');
            $table->bigInteger('material_types_id')->unsigned()->nullable();
        });
    }
};