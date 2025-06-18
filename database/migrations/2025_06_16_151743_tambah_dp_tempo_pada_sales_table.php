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
        Schema::table('sales', function (Blueprint $table) {
            // Tambah field bayar (jumlah uang yang dibayarkan customer)
            // $table->decimal('dp', 15, 2)->default(0)->after('kembali');
            // $table->integer('tempo')->nullable()->after('dp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // $table->dropColumn(['tempo']);
        });
    }
};
