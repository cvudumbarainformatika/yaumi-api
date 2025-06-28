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
        Schema::create('pembayaran_piutangs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('customer_id');
                $table->date('tanggal');
                $table->decimal('total', 15, 2)->default(0);
                $table->string('metode_pembayaran')->default('tunai'); // tunai, transfer, giro, qris
                $table->string('bank_tujuan')->nullable();
                $table->string('rekening_tujuan')->nullable();
                $table->string('nama_rekening')->nullable();
                $table->string('nomor_giro')->nullable();
                $table->date('tanggal_jatuh_tempo')->nullable();
                $table->text('keterangan')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran_piutangs');
    }
};
