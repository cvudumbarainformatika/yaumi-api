<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Jadikan customer_id nullable
            $table->foreignId('customer_id')->nullable()->change();
            // Tambah field bayar (jumlah uang yang dibayarkan customer)
            $table->decimal('bayar', 15, 2)->default(0)->after('paid');
            // Tambah field kembali (uang kembalian ke customer)
            $table->decimal('kembali', 15, 2)->default(0)->after('bayar');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Kembalikan customer_id menjadi required
            $table->foreignId('customer_id')->nullable(false)->change();
            // Hapus field bayar dan kembali
            $table->dropColumn(['bayar', 'kembali']);
        });
    }
};
