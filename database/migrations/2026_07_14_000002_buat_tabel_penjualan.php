<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penjualan', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->string('nama');
            $table->unsignedBigInteger('total_kotor');
            $table->unsignedBigInteger('total_dibulatkan');
            $table->unsignedBigInteger('dibayar')->default(0);
            $table->unsignedBigInteger('dibayar_kemarin')->default(0);
            $table->boolean('kemarin_aktif')->default(false);
            $table->bigInteger('selisih');
            $table->string('catatan')->nullable();
            $table->boolean('tombol_bayar_diklik')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualan');
    }
};
