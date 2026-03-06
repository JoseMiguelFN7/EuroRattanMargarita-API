<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            // Reemplazamos 'waiting_for_response' por 'suggestion_sent' y agregamos 'suggestion_replied'
        DB::statement("ALTER TABLE commissions MODIFY COLUMN status ENUM('created', 'suggestion_sent', 'suggestion_replied', 'approved', 'quoted', 'paid', 'rejected') DEFAULT 'created'");
        
        // Opcional: Si tenías algún pedido viejo en 'waiting_for_response', lo pasamos al nuevo estado para que no quede huérfano
        DB::table('commissions')->where('status', 'waiting_for_response')->update(['status' => 'suggestion_sent']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            DB::statement("ALTER TABLE commissions MODIFY COLUMN status ENUM('created', 'waiting_for_response', 'approved', 'quoted', 'paid', 'rejected') DEFAULT 'created'");
        });
    }
};
