<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oil_stocks', function (Blueprint $table) {
            $table->id();
            $table->decimal('qty', 8, 2);
            $table->unsignedBigInteger('price');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oil_stocks');
    }
};
