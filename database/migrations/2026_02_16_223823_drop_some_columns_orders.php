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
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['product_name']);
            $table->dropColumn(['is_natural']);
            $table->dropColumn(['subtotal']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['total_amount_usd']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
