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
        Schema::create('pembayaran_piutang_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pembayaran_piutang_id');
            $table->unsignedBigInteger('sale_id');
            $table->decimal('dibayar', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('pembayaran_piutang_id')->references('id')->on('pembayaran_piutangs')->onDelete('cascade');
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran_piutang_details');
    }
};
