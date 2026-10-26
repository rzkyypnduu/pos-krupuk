<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('potongan_saldo', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->nullable();
            $table->integer('nilai_a')->default(0);
            $table->integer('nilai_b')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('potongan_saldo');
    }
};
