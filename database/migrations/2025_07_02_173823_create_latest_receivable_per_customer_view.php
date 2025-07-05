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
            CREATE OR REPLACE VIEW latest_receivable_per_customer AS
            SELECT crh.customer_id, crh.balance_after
            FROM customer_receivable_histories crh
            JOIN (
                SELECT customer_id, MAX(id) AS max_id
                FROM customer_receivable_histories
                GROUP BY customer_id
            ) latest ON latest.customer_id = crh.customer_id AND latest.max_id = crh.id;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS latest_receivable_per_customer");
    }
};
