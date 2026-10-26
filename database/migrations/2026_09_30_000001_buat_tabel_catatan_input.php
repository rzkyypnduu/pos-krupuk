<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catatan_input', function (Blueprint $table) {
            $table->id();
            $table->string('metode', 16);
            $table->string('tab', 32)->nullable();
            $table->string('perangkat')->nullable();
            $table->text('teks_mentah')->nullable();
            $table->text('teks_normal')->nullable();
            $table->json('json_urai')->nullable();
            $table->json('json_akhir')->nullable();
            $table->json('json_cocok')->nullable();
            $table->unsignedBigInteger('durasi_ms')->nullable();
            $table->timestamp('mulai_pada')->nullable();
            $table->unsignedBigInteger('penjualan_id')->nullable();
            $table->string('status', 16);
            $table->text('kesalahan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catatan_input');
    }
};
