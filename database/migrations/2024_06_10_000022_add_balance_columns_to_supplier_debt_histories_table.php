<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_debt_histories', function (Blueprint $table) {
            $table->decimal('balance_before', 15, 2)->after('amount');
            $table->decimal('balance_after', 15, 2)->after('balance_before');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_debt_histories', function (Blueprint $table) {
            $table->dropColumn(['balance_before', 'balance_after']);
        });
    }
};