<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sisa_stok', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->nullable();
            $table->string('nama');
            $table->float('jumlah')->default(0);
            $table->unsignedBigInteger('harga')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sisa_stok');
    }
};
