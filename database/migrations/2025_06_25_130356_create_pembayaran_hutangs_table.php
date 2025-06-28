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
        Schema::create('pembayaran_hutangs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict');
            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->date('tanggal');
            $table->decimal('total', 18, 2);
            $table->enum('metode_pembayaran', ['tunai', 'transfer','qris','giro'])->default('tunai');
            $table->string('rekening_tujuan')->nullable(); // bisa isi nomor rekening / QRIS ID
            $table->string('bank_tujuan')->nullable();     // contoh: BCA, Mandiri
            $table->string('nama_rekening')->nullable();   // contoh: PT ABC Indonesia
            $table->string('nomor_giro')->nullable();      // khusus jika metode = giro
            $table->date('tanggal_jatuh_tempo')->nullable(); // jika pakai giro
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pembayaran_hutangs');
    }
};
