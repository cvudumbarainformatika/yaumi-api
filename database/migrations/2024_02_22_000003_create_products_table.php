<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('barcode')->unique();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('satuan_id')->constrained()->cascadeOnDelete();
            $table->decimal('hargabeli', 12, 2);
            $table->decimal('hargajual', 12, 2);
            $table->decimal('hargajualcust', 12, 2);
            $table->decimal('hargajualantar', 12, 2);
            $table->integer('stock');
            $table->integer('minstock');
            $table->string('rak');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};