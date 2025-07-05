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
       DB::statement("
            CREATE OR REPLACE VIEW latest_debt_per_supplier AS
            SELECT sdh.supplier_debt_id, sdh.balance_after
            FROM supplier_debt_histories sdh
            JOIN (
              SELECT supplier_debt_id, MAX(id) AS max_id
              FROM supplier_debt_histories
              GROUP BY supplier_debt_id
            ) latest ON latest.supplier_debt_id = sdh.supplier_debt_id AND latest.max_id = sdh.id
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS latest_debt_per_supplier");
    }
};
