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
        Schema::create('return_penjualan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_penjualan_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('restrict');
            $table->integer('qty')->default(0);
            $table->decimal('harga', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->enum('status', ['baik', 'rusak'])->default('baik');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_penjualan_items');
    }
};
