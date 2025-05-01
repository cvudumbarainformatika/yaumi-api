<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop table if exists first
        Schema::dropIfExists('customer_receivable_histories');
        
        Schema::create('customer_receivable_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('sales_id')->nullable()->constrained('sales')->onDelete('set null');
            $table->enum('type', ['sales', 'payment', 'adjustment', 'cancel']);
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_receivable_histories');
    }
};
