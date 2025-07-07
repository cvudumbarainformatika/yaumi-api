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
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');                  // Nama menu
            $table->string('label');                  // Nama menu
            $table->string('icon')->nullable();      // Ikon (misal: 'home', 'settings')
            $table->string('route')->nullable();     // Nama route Laravel/Vue
            $table->string('url')->nullable();       // URL manual (jika tidak pakai route)
            $table->unsignedBigInteger('parent_id')->nullable(); // Untuk sub-menu
            $table->integer('order')->default(0);    // Urutan tampil
            $table->boolean('is_active')->default(true);
            $table->string('permission')->nullable(); // Untuk ACL seperti 'menu.user.view'

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
