<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->enum('mutation_type', ['in', 'out']);
            $table->integer('qty');
            $table->string('source_type'); // contoh: purchase, sales, adjustment, return, dll
            $table->unsignedBigInteger('source_id')->nullable(); // id transaksi sumber
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_mutations');
    }
};
