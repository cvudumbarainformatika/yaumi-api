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
        Schema::create('cash_flows', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->enum('tipe', ['in', 'out']);
            $table->unsignedBigInteger('kas_id');
            $table->unsignedBigInteger('kasir_id')->nullable(); // HANYA jika tipe kas = 'kasir'
            $table->decimal('jumlah', 15, 2);
            $table->string('kategori'); // e.g. Beli ATK
            $table->text('keterangan')->nullable();
            $table->string('source_type')->nullable(); // e.g. penjualan, manual
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // yang mencatat
            $table->timestamps();

            $table->foreign('kas_id')->references('id')->on('kas')->onDelete('cascade');
            $table->foreign('kasir_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_flows');
    }
};
