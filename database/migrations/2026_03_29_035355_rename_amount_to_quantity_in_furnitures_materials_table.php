<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('furnitures_materials')) {
            Schema::table('furnitures_materials', function (Blueprint $table) {
                // Si existe 'amount', lo renombramos a 'quantity'
                if (Schema::hasColumn('furnitures_materials', 'amount')) {
                    $table->renameColumn('amount', 'quantity');
                }
                
                // Aprovechamos para asegurar que el default sea 0.00 como en tu local
                $table->decimal('quantity', 10, 2)->default(0)->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('furnitures_materials', function (Blueprint $table) {
            $table->renameColumn('quantity', 'amount');
        });
    }
};