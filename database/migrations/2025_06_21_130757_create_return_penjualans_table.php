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
        Schema::create('return_penjualans', function (Blueprint $table) {
            $table->id();
            $table->string('unique_code')->unique();
            $table->string('nota')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('sales_id')->nullable()->constrained('sales')->onDelete('set null');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->decimal('total', 18, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('return_penjualans');
    }
};
