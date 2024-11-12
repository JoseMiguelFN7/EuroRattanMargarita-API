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
        Schema::table('furniture_material', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('furniture_material', function (Blueprint $table) {
            $table->dropColumn('amount'); // Elimina el campo en caso de rollback
        });
    }
};
