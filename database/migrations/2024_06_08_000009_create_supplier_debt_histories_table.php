<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_debt_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_debt_id')->constrained('supplier_debts')->onDelete('cascade');
            $table->enum('mutation_type', ['increase', 'decrease']);
            $table->decimal('amount', 15, 2);
            $table->string('source_type'); // contoh: purchase, payment, adjustment, return, dll
            $table->unsignedBigInteger('source_id')->nullable(); // id transaksi sumber
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_debt_histories');
    }
};
