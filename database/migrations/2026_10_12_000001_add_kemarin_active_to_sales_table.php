<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('kemarin_active')->default(false)->after('paid_kemarin');
        });

        // backfill: transaksi yang sudah punya paid_kemarin dianggap aktif
        DB::table('sales')->where('paid_kemarin', '>', 0)->update(['kemarin_active' => true]);
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('kemarin_active');
        });
    }
};
