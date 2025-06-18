<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_activities', function (Blueprint $table) {
            // Hapus foreign key constraint jika ada
            $table->dropForeign(['user_id']);

            // Ubah jadi nullable
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // Tambah index manual
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_activities', function (Blueprint $table) {
            // Drop index manual
            $table->dropIndex(['user_id']);

            // Ubah jadi not nullable
            $table->unsignedBigInteger('user_id')->nullable(false)->change();

            // Tambahkan kembali foreign key + cascade
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
