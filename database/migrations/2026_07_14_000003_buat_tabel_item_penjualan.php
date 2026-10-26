<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_penjualan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penjualan_id')->constrained('penjualan')->cascadeOnDelete();
            $table->foreignId('produk_id')->nullable()->constrained('produk')->nullOnDelete();
            $table->string('nama');
            $table->decimal('jumlah', 8, 2);
            $table->unsignedBigInteger('harga');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_penjualan');
    }
};
