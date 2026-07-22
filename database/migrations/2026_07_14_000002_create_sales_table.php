<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('name'); // nama pelanggan
            $table->unsignedBigInteger('raw_total');
            $table->unsignedBigInteger('rounded_total');
            $table->unsignedBigInteger('paid');
            $table->bigInteger('diff'); // rounded_total - paid ( + = tambah hutang, - = kurangi hutang )
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
