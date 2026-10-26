<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lembar_hasil', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->date('tanggal_sumber')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lembar_hasil');
    }
};
