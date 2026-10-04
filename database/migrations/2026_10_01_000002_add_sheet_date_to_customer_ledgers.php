<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_ledgers', function (Blueprint $table) {
            $table->date('sheet_date')->nullable()->after('date')->index();
        });

        // Data lama menjadi milik lembar tanggal hari ini (rekap hutang pelanggan
        // tetap tampil penuh pada tanggal aktif saat pertama kali fitur ini aktif).
        DB::table('customer_ledgers')->whereNull('sheet_date')->update([
            'sheet_date' => now()->toDateString(),
        ]);
    }

    public function down(): void
    {
        Schema::table('customer_ledgers', function (Blueprint $table) {
            $table->dropIndex(['sheet_date']);
            $table->dropColumn('sheet_date');
        });
    }
};
