<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ubah purchase_order_id menjadi nullable di tabel purchases
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->change();
        });

        // Ubah purchase_order_item_id menjadi nullable di tabel purchase_items
        Schema::table('purchase_items', function (Blueprint $table) {
            // Jika kolom sudah ada, ubah menjadi nullable
            if (Schema::hasColumn('purchase_items', 'purchase_order_item_id')) {
                $table->foreignId('purchase_order_item_id')->nullable()->change();
            } 
            // Jika kolom belum ada, tambahkan sebagai nullable
            else {
                $table->foreignId('purchase_order_item_id')->nullable()->after('purchase_id')->constrained('purchase_order_items')->onDelete('restrict');
            }
        });
    }

    public function down(): void
    {
        // Kembalikan purchase_order_id menjadi required di tabel purchases
        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable(false)->change();
        });

        // Kembalikan purchase_order_item_id menjadi required di tabel purchase_items
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')->nullable(false)->change();
        });
    }
};