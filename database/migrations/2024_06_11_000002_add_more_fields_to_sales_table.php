<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_method', 30)->nullable()->after('paid');
            $table->decimal('discount', 15, 2)->default(0)->after('payment_method');
            $table->decimal('tax', 15, 2)->default(0)->after('discount');
            $table->string('reference', 50)->nullable()->after('tax');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'discount', 'tax', 'reference']);
        });
    }
};
