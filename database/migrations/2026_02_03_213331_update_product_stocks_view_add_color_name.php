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
        DB::statement("
            CREATE OR REPLACE VIEW product_stocks AS
            SELECT 
                p.id AS productID,
                p.code AS productCode,
                c.hex AS color,
                c.name AS color_name,
                COALESCE(SUM(pm.quantity), 0) AS stock
            FROM products p
            LEFT JOIN products_colors pc ON pc.product_id = p.id
            LEFT JOIN colors c ON pc.color_id = c.id
            LEFT JOIN product_movements pm ON pm.product_id = p.id AND (pm.color_id = c.id OR c.id IS NULL)
            GROUP BY 
                p.id, 
                p.code, 
                c.hex, 
                c.name
            ORDER BY 
                p.code, 
                c.name
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            CREATE OR REPLACE VIEW product_stocks AS
            SELECT 
                p.id AS productID,
                p.code AS productCode,
                c.hex AS color,
                COALESCE(SUM(pm.quantity), 0) AS stock
            FROM products p
            LEFT JOIN products_colors pc ON pc.product_id = p.id
            LEFT JOIN colors c ON pc.color_id = c.id
            LEFT JOIN product_movements pm ON pm.product_id = p.id AND (pm.color_id = c.id OR c.id IS NULL)
            GROUP BY 
                p.id, 
                p.code, 
                c.hex
            ORDER BY 
                p.code, 
                c.hex
        ");
    }
};
