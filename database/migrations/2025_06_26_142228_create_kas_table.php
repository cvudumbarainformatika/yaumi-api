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
        Schema::create('kas', function (Blueprint $table) {
            $table->id();
            $table->string('nama'); // e.g. Kasir A, Kas Pusat, Bank BCA
            $table->enum('tipe', ['kasir', 'bank', 'pusat']);
            $table->string('bank_name')->nullable();      // untuk bank
            $table->string('no_rekening')->nullable();    // untuk bank
            $table->decimal('saldo_awal', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kas');
    }
};
