<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buku_pelanggan', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->date('tanggal_lembar')->nullable();
            $table->string('nama');
            $table->unsignedBigInteger('jumlah');
            $table->enum('jenis', ['tambah', 'bayar']);
            $table->string('catatan')->nullable();
            $table->foreignId('penjualan_id')->nullable()->constrained('penjualan')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buku_pelanggan');
    }
};
