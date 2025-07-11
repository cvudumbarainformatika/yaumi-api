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
        Schema::create('return_pembelians', function (Blueprint $table) {
            $table->id();
            $table->string('unique_code')->unique();
            $table->string('nota')->nullable();
            $table->foreignId('supplier_id')->constrained()->onDelete('restrict');
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('total', 18, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_pembelians');
    }
};
