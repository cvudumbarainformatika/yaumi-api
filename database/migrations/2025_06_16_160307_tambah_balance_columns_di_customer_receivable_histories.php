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
        Schema::table('customer_receivable_histories', function (Blueprint $table) {
            // $table->decimal('balance_before', 15, 2)->after('amount');
            // $table->decimal('balance_after', 15, 2)->after('balance_before');
            // $table->string('source_type'); // contoh: purchase, payment, adjustment, return, dll
            // $table->unsignedBigInteger('source_id')->nullable(); // id transaksi sumber
        });
    }

    public function down(): void
    {
        Schema::table('customer_receivable_histories', function (Blueprint $table) {
            // $table->dropColumn(['balance_before', 'balance_after', 'source_type', 'source_id']);
        });
    }
};
