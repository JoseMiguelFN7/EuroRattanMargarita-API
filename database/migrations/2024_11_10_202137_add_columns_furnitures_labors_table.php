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
        Schema::table('furnitures_labors', function (Blueprint $table) {
            $table->decimal('days', 10, 2)->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('furnitures_labors', function (Blueprint $table) {
            $table->dropColumn('days'); // Elimina el campo en caso de rollback
        });
    }
};
