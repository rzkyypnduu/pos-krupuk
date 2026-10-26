<?php

namespace Tests\Feature;

use App\Models\BukuPelanggan;
use App\Models\CatatanInput;
use App\Models\Pengeluaran;
use App\Models\Penjualan;
use App\Models\Produk;
use Tests\TestCase;

/**
 * Pengujian reset data, edit transaksi, dan format angka bertitik. Data uji
 * memakai tanggal 2099-12 agar tidak menyentuh data lain, lalu dibersihkan
 * di akhir tiap test.
 */
class PerbaikanBugTest extends TestCase
{
    public function test_reset_month_reads_bulan_from_post_body(): void
    {
        $penjualan = Penjualan::create([
            'tanggal' => '2099-12-15', 'nama' => 'UjiResetBulan',
            'total_kotor' => 1000, 'total_dibulatkan' => 1000, 'dibayar' => 0, 'selisih' => 1000,
        ]);
        $pengeluaran = Pengeluaran::create(['tanggal' => '2099-12-15', 'jumlah' => 500]);
        $bulanLain = Penjualan::create([
            'tanggal' => '2099-11-15', 'nama' => 'UjiResetBulanLain',
            'total_kotor' => 2000, 'total_dibulatkan' => 2000, 'dibayar' => 0, 'selisih' => 2000,
        ]);

        try {
            // Seperti form di tab Ringkasan: bulan dikirim sebagai hidden input POST.
            $response = $this->post(route('pos.resetMonth'), ['bulan' => '2099-12']);

            $response->assertRedirect();
            $response->assertSessionMissing('errors');
            $this->assertDatabaseMissing('penjualan', ['id' => $penjualan->id]);
            $this->assertDatabaseMissing('pengeluaran', ['id' => $pengeluaran->id]);
            // bulan lain tidak boleh tersentuh
            $this->assertDatabaseHas('penjualan', ['id' => $bulanLain->id]);
        } finally {
            Penjualan::whereIn('id', [$penjualan->id, $bulanLain->id])->delete();
            Pengeluaran::where('id', $pengeluaran->id)->delete();
        }
    }

    public function test_reset_month_without_bulan_fails_and_keeps_data(): void
    {
        $penjualan = Penjualan::create([
            'tanggal' => '2099-12-16', 'nama' => 'UjiResetKosong',
            'total_kotor' => 1000, 'total_dibulatkan' => 1000, 'dibayar' => 0, 'selisih' => 1000,
        ]);

        try {
            $response = $this->post(route('pos.resetMonth'), []);

            $response->assertSessionHasErrors('bulan');
            $this->assertDatabaseHas('penjualan', ['id' => $penjualan->id]);
        } finally {
            $penjualan->delete();
        }
    }

    public function test_reset_all_deletes_all_data(): void
    {
        $produk = Produk::create([
            'nama' => 'UjiResetSemua_'.uniqid(),
            'harga' => 1000,
        ]);

        $this->post(route('pos.resetAll'))
            ->assertRedirect(route('pos', ['tab' => 'transaksi']));

        $this->assertDatabaseMissing('produk', ['id' => $produk->id]);
    }

    public function test_edit_with_missing_sale_id_is_rejected_without_fake_success(): void
    {
        $produk = Produk::firstOrCreate(['nama' => 'UjiProdukDasar'], ['harga' => 10000]);

        $sebelum = Penjualan::count();

        $response = $this->post(route('pos.simpanTransaksi'), [
            'txName' => 'UjiC4Hantu',
            'txDate' => '2099-12-15',
            'qty' => [$produk->id => 1],
            'editing_sale_id' => 999999,
            'tx_paid_touched' => 0,
        ]);

        $response->assertSessionHasErrors('editing_sale_id');
        // tidak ada transaksi tersimpan, tidak ada pesan sukses palsu
        $this->assertSame($sebelum, Penjualan::count());
        $response->assertSessionMissing('success');
        $this->assertDatabaseMissing('penjualan', ['nama' => 'UjiC4Hantu']);
    }

    public function test_edit_with_valid_sale_id_still_saves(): void
    {
        $produk = Produk::firstOrCreate(['nama' => 'UjiProdukDasar'], ['harga' => 10000]);

        $penjualan = Penjualan::create([
            'tanggal' => '2099-12-17', 'nama' => 'UjiC4Asli',
            'total_kotor' => 0, 'total_dibulatkan' => 0, 'dibayar' => 0, 'selisih' => 0,
        ]);

        try {
            $response = $this->post(route('pos.simpanTransaksi'), [
                'txName' => 'UjiC4AsliEdit',
                'txDate' => '2099-12-17',
                'qty' => [$produk->id => 1],
                'editing_sale_id' => $penjualan->id,
                'tx_paid_touched' => 0,
            ]);

            $response->assertSessionHas('success');
            $response->assertSessionHasNoErrors();
            $this->assertDatabaseHas('penjualan', ['id' => $penjualan->id, 'nama' => 'UjiC4AsliEdit']);
        } finally {
            Penjualan::where('nama', 'UjiC4AsliEdit')->delete();
            CatatanInput::where('penjualan_id', $penjualan->id)->delete();
        }
    }

    public function test_dotted_expense_amount_is_saved_as_full_number(): void
    {
        try {
            $response = $this->post(route('pos.simpanExpense'), [
                'expDate' => '2099-12-20',
                'expAmount' => '15.000',
                'expNote' => 'UjiTitikExpense',
            ]);

            $response->assertSessionHasNoErrors();
            $this->assertDatabaseHas('pengeluaran', [
                'tanggal' => '2099-12-20', 'jumlah' => 15000, 'catatan' => 'UjiTitikExpense',
            ]);
        } finally {
            Pengeluaran::where('catatan', 'UjiTitikExpense')->delete();
        }
    }

    public function test_dotted_product_price_is_saved_as_full_number(): void
    {
        try {
            $response = $this->post(route('pos.simpanProduk'), [
                'prodName' => 'UjiTitikProduk',
                'prodPrice' => '15.000',
            ]);

            $response->assertSessionHasNoErrors();
            $this->assertDatabaseHas('produk', ['nama' => 'UjiTitikProduk', 'harga' => 15000]);
        } finally {
            Produk::where('nama', 'UjiTitikProduk')->delete();
        }
    }

    public function test_dotted_debt_modal_amount_passes_integer_validation(): void
    {
        try {
            $response = $this->post(route('pos.simpanHutangPelanggan'), [
                'debtModalName' => 'UjiTitikHutang',
                'debtModalAmount' => '25.000',
                'debtModalDate' => '2099-12-20',
            ]);

            $response->assertSessionHasNoErrors();
            $this->assertDatabaseHas('buku_pelanggan', [
                'nama' => 'UjiTitikHutang', 'jumlah' => 25000,
            ]);
        } finally {
            BukuPelanggan::where('nama', 'UjiTitikHutang')->delete();
        }
    }
}
