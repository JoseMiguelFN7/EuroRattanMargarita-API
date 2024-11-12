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
        Schema::rename('furniture_material', 'furnitures_materials');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('furnitures_materials', 'furniture_material');
    }
};
