<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
       
         DB::statement(<<<SQL
            CREATE OR REPLACE VIEW latest_stock_per_product AS
            SELECT
                psm.product_id,
                psm.stock_after AS stock,
                psm.created_at AS tanggal
            FROM product_stock_mutations psm
            JOIN (
                SELECT product_id, MAX(id) AS max_id
                FROM product_stock_mutations
                GROUP BY product_id
            ) latest ON latest.product_id = psm.product_id AND latest.max_id = psm.id;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         DB::statement(<<<SQL
            CREATE OR REPLACE VIEW latest_stock_per_product AS
            SELECT
                psm.product_id,
                psm.stock_after AS stock
            FROM product_stock_mutations psm
            JOIN (
                SELECT product_id, MAX(id) AS max_id
                FROM product_stock_mutations
                GROUP BY product_id
            ) latest ON latest.product_id = psm.product_id AND latest.max_id = psm.id;
        SQL);
    }
};
