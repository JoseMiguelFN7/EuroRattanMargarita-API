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
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->boolean('applies_igtf')->default(false)->after('is_active');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('igtf_amount', 10, 2)->default(0.00)->after('exchange_rate');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('igtf_amount', 10, 2)->default(0.00)->after('total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('applies_igtf');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('igtf_amount');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('igtf_amount');
        });
    }
};
