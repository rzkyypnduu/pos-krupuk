<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Indeks untuk query harian/bulanan POS: filter tanggal, nama pelanggan,
     * dan relasi catatan input ke transaksi.
     */
    public function up(): void
    {
        Schema::table('penjualan', function (Blueprint $table) {
            $table->index('tanggal', 'penjualan_tanggal_index');
            $table->index('nama', 'penjualan_nama_index');
            $table->index(['tanggal', 'nama'], 'penjualan_tanggal_nama_index');
        });

        Schema::table('buku_pelanggan', function (Blueprint $table) {
            $table->index('tanggal', 'buku_pelanggan_tanggal_index');
            $table->index('nama', 'buku_pelanggan_nama_index');
            $table->index(['nama', 'tanggal_lembar'], 'buku_pelanggan_nama_lembar_index');
        });

        Schema::table('buku_pribadi', function (Blueprint $table) {
            $table->index('tanggal', 'buku_pribadi_tanggal_index');
            $table->index('nama', 'buku_pribadi_nama_index');
        });

        Schema::table('stok_minyak', function (Blueprint $table) {
            $table->index('tanggal', 'stok_minyak_tanggal_index');
        });

        Schema::table('manajemen_stok', function (Blueprint $table) {
            $table->index('tanggal', 'manajemen_stok_tanggal_index');
            $table->index('nama', 'manajemen_stok_nama_index');
        });

        Schema::table('sisa_stok', function (Blueprint $table) {
            $table->index('tanggal', 'sisa_stok_tanggal_index');
            $table->index('nama', 'sisa_stok_nama_index');
        });

        Schema::table('potongan_saldo', function (Blueprint $table) {
            $table->index('tanggal', 'potongan_saldo_tanggal_index');
        });

        Schema::table('pengeluaran', function (Blueprint $table) {
            $table->index('tanggal', 'pengeluaran_tanggal_index');
        });

        Schema::table('produk', function (Blueprint $table) {
            $table->index('nama', 'produk_nama_index');
        });

        Schema::table('catatan_input', function (Blueprint $table) {
            $table->index('penjualan_id', 'catatan_input_penjualan_index');
            $table->index('created_at', 'catatan_input_created_index');
            $table->index(['metode', 'created_at'], 'catatan_input_metode_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('penjualan', function (Blueprint $table) {
            $table->dropIndex('penjualan_tanggal_index');
            $table->dropIndex('penjualan_nama_index');
            $table->dropIndex('penjualan_tanggal_nama_index');
        });

        Schema::table('buku_pelanggan', function (Blueprint $table) {
            $table->dropIndex('buku_pelanggan_tanggal_index');
            $table->dropIndex('buku_pelanggan_nama_index');
            $table->dropIndex('buku_pelanggan_nama_lembar_index');
        });

        Schema::table('buku_pribadi', function (Blueprint $table) {
            $table->dropIndex('buku_pribadi_tanggal_index');
            $table->dropIndex('buku_pribadi_nama_index');
        });

        Schema::table('stok_minyak', function (Blueprint $table) {
            $table->dropIndex('stok_minyak_tanggal_index');
        });

        Schema::table('manajemen_stok', function (Blueprint $table) {
            $table->dropIndex('manajemen_stok_tanggal_index');
            $table->dropIndex('manajemen_stok_nama_index');
        });

        Schema::table('sisa_stok', function (Blueprint $table) {
            $table->dropIndex('sisa_stok_tanggal_index');
            $table->dropIndex('sisa_stok_nama_index');
        });

        Schema::table('potongan_saldo', function (Blueprint $table) {
            $table->dropIndex('potongan_saldo_tanggal_index');
        });

        Schema::table('pengeluaran', function (Blueprint $table) {
            $table->dropIndex('pengeluaran_tanggal_index');
        });

        Schema::table('produk', function (Blueprint $table) {
            $table->dropIndex('produk_nama_index');
        });

        Schema::table('catatan_input', function (Blueprint $table) {
            $table->dropIndex('catatan_input_penjualan_index');
            $table->dropIndex('catatan_input_created_index');
            $table->dropIndex('catatan_input_metode_created_index');
        });
    }
};
