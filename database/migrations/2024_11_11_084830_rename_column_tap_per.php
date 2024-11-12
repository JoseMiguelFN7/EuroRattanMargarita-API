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
            $table->renameColumn('labor_fab_per', 'labor_fab_per');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('furnitures', function (Blueprint $table) {
            $table->renameColumn('labor_fab_per', 'labor_fab_per');
        });
    }
};
