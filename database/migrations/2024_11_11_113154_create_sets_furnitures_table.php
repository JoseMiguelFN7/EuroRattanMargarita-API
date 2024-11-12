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
        Schema::create('sets_furnitures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('set_id');
            $table->unsignedBigInteger('furniture_id');
            $table->integer('amount');
            $table->timestamps();
            
            $table->foreign('set_id')
                ->references('id')->on('sets')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('furniture_id')
                ->references('id')->on('furnitures')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sets_furnitures');
    }
};
