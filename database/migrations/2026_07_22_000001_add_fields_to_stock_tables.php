<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oil_stocks', function (Blueprint $table) {
            $table->date('date')->nullable()->after('id');
        });

        Schema::table('stock_managements', function (Blueprint $table) {
            $table->date('date')->nullable()->after('id');
            $table->json('batches')->nullable()->after('price');
        });

        Schema::table('stock_remainings', function (Blueprint $table) {
            $table->date('date')->nullable()->after('id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('is_paid_btn_clicked')->default(false)->after('note');
        });

        Schema::table('saldo_deductions', function (Blueprint $table) {
            $table->date('date')->nullable()->after('id');
            $table->string('note')->nullable()->after('b');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedBigInteger('amount');
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');

        Schema::table('saldo_deductions', function (Blueprint $table) {
            $table->dropColumn(['date', 'note']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('is_paid_btn_clicked');
        });

        Schema::table('stock_remainings', function (Blueprint $table) {
            $table->dropColumn('date');
        });

        Schema::table('stock_managements', function (Blueprint $table) {
            $table->dropColumn(['date', 'batches']);
        });

        Schema::table('oil_stocks', function (Blueprint $table) {
            $table->dropColumn('date');
        });
    }
};
