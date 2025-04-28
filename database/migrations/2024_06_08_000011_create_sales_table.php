<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->decimal('total', 15, 2);
            $table->decimal('paid', 15, 2)->default(0);
            $table->enum('status', ['draft', 'completed', 'cancelled']);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->string('unique_code', 20)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
