<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_managements', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // nama pemegang / gudang, mis. gun, imam
            $table->decimal('qty', 8, 2);
            $table->unsignedBigInteger('price');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_managements');
    }
};
