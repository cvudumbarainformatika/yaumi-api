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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');                  // Nama Perusahaan / Toko
            $table->string('owner_name')->nullable(); // Nama Pemilik
            $table->string('phone')->nullable();     // Nomor Telepon
            $table->string('email')->nullable();     // Email
            $table->string('address')->nullable();   // Alamat
            $table->string('logo')->nullable();      // Path Logo
            $table->string('npwp')->nullable();      // NPWP
            $table->string('tax_label')->nullable(); // Misal: PPN 10%
            $table->boolean('is_tax_active')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
