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
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('min_stock', 10, 2)->default(0)->after('unit_id');
            $table->decimal('max_stock', 10, 2)->nullable()->after('min_stock');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn(['min_stock', 'max_stock']);
        });
    }
};
