<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_ledgers', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name'); // nama pelanggan
            $table->unsignedBigInteger('amount');
            $table->enum('type', ['tambah', 'bayar']); // tambah = nambah hutang, bayar = bayar hutang
            $table->string('note')->nullable();
            $table->foreignId('sale_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledgers');
    }
};
