<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_debt_histories', function (Blueprint $table) {
            // Ubah mutation_type dengan default 'increase'
            $table->enum('mutation_type', ['increase', 'decrease'])->default('increase')->change();
            
            // Ubah amount dengan default 0
            $table->decimal('amount', 15, 2)->default(0)->change();
            
            // Ubah balance_before dengan default 0
            $table->decimal('balance_before', 15, 2)->default(0)->change();
            
            // Ubah balance_after dengan default 0
            $table->decimal('balance_after', 15, 2)->default(0)->change();
            
            // Ubah source_type dengan default 'manual'
            $table->string('source_type')->default('manual')->change();
            
            // Notes sudah nullable, tidak perlu diubah
        });
    }

    public function down(): void
    {
        Schema::table('supplier_debt_histories', function (Blueprint $table) {
            // Kembalikan ke nilai asli tanpa default
            $table->enum('mutation_type', ['increase', 'decrease'])->change();
            $table->decimal('amount', 15, 2)->change();
            $table->decimal('balance_before', 15, 2)->change();
            $table->decimal('balance_after', 15, 2)->change();
            $table->string('source_type')->change();
        });
    }
};