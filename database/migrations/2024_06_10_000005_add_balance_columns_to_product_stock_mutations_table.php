<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stock_mutations', function (Blueprint $table) {
            $table->integer('stock_before')->after('qty');
            $table->integer('stock_after')->after('stock_before');
        });
    }

    public function down(): void
    {
        Schema::table('product_stock_mutations', function (Blueprint $table) {
            $table->dropColumn(['stock_before', 'stock_after']);
        });
    }
};