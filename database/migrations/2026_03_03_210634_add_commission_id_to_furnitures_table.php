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
            $table->foreignId('commission_id')
              ->nullable()
              ->after('id')
              ->constrained()
              ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('furnitures', function (Blueprint $table) {
            $table->dropForeign(['commission_id']);
            $table->dropColumn('commission_id');
        });
    }
};
