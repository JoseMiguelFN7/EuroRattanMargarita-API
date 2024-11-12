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
        Schema::create('furniture_labor', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('furniture_id');
            $table->unsignedBigInteger('labor_id');
            $table->timestamps();

            $table->foreign('furniture_id')
                ->references('id')->on('furnitures')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('labor_id')
            ->references('id')->on('labors')
            ->onDelete('cascade')
            ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('furniture_labor');
    }
};