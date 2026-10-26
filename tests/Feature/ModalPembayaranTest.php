<?php

namespace Tests\Feature;

use App\Models\BukuPelanggan;
use App\Models\ItemPenjualan;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Services\ServiceHutang;
use Tests\TestCase;

class ModalPembayaranTest extends TestCase
{
    private function hutang(): ServiceHutang
    {
        return app(ServiceHutang::class);
    }

    /** Ambil isi `<tr>` tabel transaksi yang berisi sale name tertentu (assert per-baris). */
    private function rowOfSale(string $html, string $nama): string
    {
        preg_match_all('/<tr\b[^>]*>.*?<\/tr>/s', $html, $daftarBaris);
        foreach ($daftarBaris[0] as $baris) {
            if (str_contains($baris, $nama)) {
                return $baris;
            }
        }
        $this->fail('Baris transaksi untuk "'.$nama.'" tidak ditemukan di render.');
    }

    public function test_payment_only_transaction_is_saved_without_products(): void
    {
        $nama = 'HutangOnly_'.uniqid();

        $respons = $this->post('/pos/transaksi?tab=transaksi&tx_date=2026-09-30', [
            'txName' => $nama,
            'txDate' => '2026-09-30',
            'tx_paid_touched' => 1,
            'tx_paid' => '75.000',
        ]);

        $respons->assertSessionHasNoErrors();

        $penjualan = Penjualan::where('nama', $nama)->first();
        $this->assertNotNull($penjualan, 'Transaksi tanpa produk harus tersimpan.');
        $this->assertSame(0, $penjualan->total_dibulatkan);
        $this->assertSame(75000, $penjualan->dibayar);
        $this->assertSame(-75000, $penjualan->selisih);
        $this->assertTrue($penjualan->tombol_bayar_diklik);

        // transaksi TIDAK menulis customer_ledgers — tabel hutang di tab Hasil
        // hanya berisi catatan manual & riwayat lama
        $daftarEntri = BukuPelanggan::where('nama', $nama)->get();
        $this->assertCount(0, $daftarEntri);

        $prosesHutang = $this->hutang()->prosesHutangPelanggan($nama);
        $this->assertSame(0, $prosesHutang['totalSisa']);
        $this->assertSame(0, $prosesHutang['deposit']);
    }

    public function test_payment_only_transaction_requires_paid_amount(): void
    {
        $respons = $this->post('/pos/transaksi?tab=transaksi&tx_date=2026-09-30', [
            'txName' => 'TanpaProdukTanpaBayar_'.uniqid(),
            'txDate' => '2026-09-30',
            'tx_paid_touched' => 0,
        ]);

        $respons->assertSessionHasErrors('txQty');
    }

    public function test_bayar_modal_with_two_forms_pays_yesterday_and_today(): void
    {
        $nama = 'BayarKmr_'.uniqid();
        BukuPelanggan::create([
            'tanggal' => '2026-09-29', 'nama' => $nama, 'jumlah' => 200000,
            'jenis' => 'tambah', 'catatan' => 'Transaksi kemarin',
        ]);
        $penjualan = Penjualan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'total_kotor' => 150000,
            'total_dibulatkan' => 150000, 'dibayar' => 0, 'selisih' => 150000,
        ]);
        BukuPelanggan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'jumlah' => 150000,
            'jenis' => 'tambah', 'catatan' => 'Transaksi', 'penjualan_id' => $penjualan->id,
        ]);

        $respons = $this->post('/pos/transaksi/'.$penjualan->id.'/bayar', [
            'pakai_kemarin' => 1,
            'bayar_kemarin' => '200.000',
            'bayar_hari_ini' => '100.000',
        ]);

        $respons->assertSessionHasNoErrors();

        $penjualan->refresh();
        $this->assertSame(100000, $penjualan->dibayar);
        $this->assertSame(50000, $penjualan->selisih);
        $this->assertSame(200000, $penjualan->dibayar_kemarin);
        $this->assertTrue($penjualan->tombol_bayar_diklik);

        // Pembayaran tidak menambah entri "bayar" ke tabel hutang.
        $this->assertSame(0, BukuPelanggan::where('penjualan_id', $penjualan->id)
            ->where('jenis', 'bayar')->count());
        // Tabel hutang tidak berkurang; entri data uji "tambah" tetap utuh.
        $prosesHutang = $this->hutang()->prosesHutangPelanggan($nama);
        $this->assertSame(350000, $prosesHutang['totalSisa']);
        $this->assertSame(0, $prosesHutang['deposit']);
    }

    public function test_debts_are_allocated_newest_first(): void
    {
        $nama = 'Lifo_'.uniqid();
        BukuPelanggan::create([
            'tanggal' => '2026-09-28', 'nama' => $nama, 'jumlah' => 100000,
            'jenis' => 'tambah', 'catatan' => 'Hutang lama',
        ]);
        BukuPelanggan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'jumlah' => 150000,
            'jenis' => 'tambah', 'catatan' => 'Hutang baru',
        ]);
        BukuPelanggan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'jumlah' => 120000,
            'jenis' => 'bayar', 'catatan' => 'Bayar',
        ]);

        $prosesHutang = $this->hutang()->prosesHutangPelanggan($nama);

        // Entri pengurang 120rb dialokasikan ke hutang terbaru (150rb) lebih
        // dahulu; sisa 30rb, sedangkan hutang lama 100rb tetap utuh.
        $this->assertCount(2, $prosesHutang['activeDebts']);
        $this->assertSame('2026-09-28', $prosesHutang['activeDebts'][0]['date']);
        $this->assertSame(100000, $prosesHutang['activeDebts'][0]['remaining']);
        $this->assertSame('2026-09-30', $prosesHutang['activeDebts'][1]['date']);
        $this->assertSame(30000, $prosesHutang['activeDebts'][1]['remaining']);
        $this->assertSame(130000, $prosesHutang['totalSisa']);
        $this->assertSame(0, $prosesHutang['deposit']);
    }

    public function test_bayar_modal_overpay_does_not_write_ledger(): void
    {
        $nama = 'LebihBayar_'.uniqid();
        BukuPelanggan::create([
            'tanggal' => '2026-09-29', 'nama' => $nama, 'jumlah' => 100000,
            'jenis' => 'tambah', 'catatan' => 'Transaksi kemarin',
        ]);
        $penjualan = Penjualan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'total_kotor' => 100000,
            'total_dibulatkan' => 100000, 'dibayar' => 0, 'selisih' => 100000,
        ]);
        BukuPelanggan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'jumlah' => 100000,
            'jenis' => 'tambah', 'catatan' => 'Transaksi', 'penjualan_id' => $penjualan->id,
        ]);

        $this->post('/pos/transaksi/'.$penjualan->id.'/bayar', [
            'pakai_kemarin' => 1,
            'bayar_kemarin' => '250.000',
            'bayar_hari_ini' => '0',
        ])->assertSessionHasNoErrors();

        $penjualan->refresh();
        $this->assertSame(250000, $penjualan->dibayar_kemarin);
        $this->assertSame(0, $penjualan->dibayar);

        // Pembayaran tidak mengubah tabel hutang: data uji tetap 200rb, deposit 0.
        $prosesHutang = $this->hutang()->prosesHutangPelanggan($nama);
        $this->assertSame(200000, $prosesHutang['totalSisa']);
        $this->assertSame(0, $prosesHutang['deposit']);
        $this->assertSame(0, BukuPelanggan::where('penjualan_id', $penjualan->id)
            ->where('jenis', 'bayar')->count());
    }

    public function test_bayar_modal_accepts_zero(): void
    {
        $penjualan = Penjualan::create([
            'tanggal' => '2026-09-30', 'nama' => 'Kosong_'.uniqid(),
            'total_kotor' => 50000, 'total_dibulatkan' => 50000, 'dibayar' => 0, 'selisih' => 50000,
        ]);

        // isian 0 / kosong diperbolehkan (mungkin memang belum bayar sama sekali)
        $this->post('/pos/transaksi/'.$penjualan->id.'/bayar', [
            'bayar_kemarin' => '',
            'bayar_hari_ini' => '',
        ])->assertSessionHasNoErrors();

        $penjualan->refresh();
        $this->assertSame(0, $penjualan->dibayar);
        $this->assertSame(50000, $penjualan->selisih);
        $this->assertSame(0, BukuPelanggan::where('penjualan_id', $penjualan->id)
            ->where('jenis', 'bayar')->count());

        // tombol Bayar dipencet (meski nominal 0/kosong) → tandai selesai, tombol jadi abu
        $this->assertTrue($penjualan->tombol_bayar_diklik);

        $nama = $penjualan->nama;
        $html = $this->get('/pos?tab=transaksi&tx_date=2026-09-30')->getContent();
        $this->assertMatchesRegularExpression(
            '/class="btn btn-outline-secondary"[^>]*title="Buka modal pembayaran '.preg_quote($nama, '/').'"/',
            $html
        );
    }

    public function test_zero_payment_keeps_button_gray_after_edit(): void
    {
        $nama = 'AbuEdit_'.uniqid();
        $produk = Produk::create(['nama' => 'P_'.uniqid(), 'harga' => 10000]);
        $penjualan = Penjualan::create([
            'tanggal' => now()->toDateString(), 'nama' => $nama, 'total_kotor' => 0,
            'total_dibulatkan' => 0, 'dibayar' => 0, 'selisih' => 0, 'tombol_bayar_diklik' => false,
        ]);
        ItemPenjualan::create(['penjualan_id' => $penjualan->id, 'produk_id' => $produk->id,
            'nama' => $produk->nama, 'jumlah' => 1, 'harga' => 10000]);

        // Bayar dengan nominal 0 → status abu
        $this->post('/pos/transaksi/'.$penjualan->id.'/bayar', [
            'bayar_hari_ini' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($penjualan->refresh()->tombol_bayar_diklik);

        // edit transaksi (mis. ubah qty) tidak boleh menurunkan status abu kembali
        $this->post('/pos/transaksi', [
            'editing_sale_id' => $penjualan->id,
            'txName' => $nama,
            'txDate' => now()->toDateString(),
            'qty' => [$produk->id => '2'],
            'tx_paid_touched' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertTrue($penjualan->refresh()->tombol_bayar_diklik,
            'Edit tidak boleh mereset status abu yang sudah di-set lewat modal Bayar.');

        $html = $this->get('/pos?tab=transaksi')->getContent();
        $this->assertMatchesRegularExpression(
            '/class="btn btn-outline-secondary"[^>]*title="Buka modal pembayaran '.preg_quote($nama, '/').'"/',
            $html
        );

        $produk->delete();
    }

    public function test_repaying_replaces_previous_payment(): void
    {
        $nama = 'Koreksi_'.uniqid();
        $penjualan = Penjualan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'total_kotor' => 50000,
            'total_dibulatkan' => 50000, 'dibayar' => 0, 'selisih' => 50000,
        ]);
        BukuPelanggan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'jumlah' => 50000,
            'jenis' => 'tambah', 'catatan' => 'Transaksi', 'penjualan_id' => $penjualan->id,
        ]);

        // Simpan pembayaran 15rb.
        $this->post('/pos/transaksi/'.$penjualan->id.'/bayar', [
            'bayar_hari_ini' => '15.000',
        ])->assertSessionHasNoErrors();

        $penjualan->refresh();
        $this->assertSame(15000, $penjualan->dibayar);
        $this->assertSame(35000, $penjualan->selisih);

        // koreksi: input 18rb → input terakhir MENANG (bukan 15rb + 18rb = 33rb)
        $this->post('/pos/transaksi/'.$penjualan->id.'/bayar', [
            'bayar_hari_ini' => '18.000',
        ])->assertSessionHasNoErrors();

        $penjualan->refresh();
        $this->assertSame(18000, $penjualan->dibayar, 'Pembayaran kedua harus menggantikan, bukan menumpuk.');
        $this->assertSame(32000, $penjualan->selisih);

        // pembayaran tidak menulis ledger sama sekali
        $this->assertSame(0, BukuPelanggan::where('penjualan_id', $penjualan->id)
            ->where('jenis', 'bayar')->count());
    }

    public function test_dibayar_column_shows_sum_of_both_forms(): void
    {
        $nama = 'DuaForm_'.uniqid();
        Penjualan::create([
            'tanggal' => now()->toDateString(), 'nama' => $nama, 'total_kotor' => 100000,
            'total_dibulatkan' => 100000, 'dibayar' => 40000, 'dibayar_kemarin' => 60000, 'selisih' => 60000,
        ]);

        $html = $this->get('/pos?tab=transaksi')->getContent();

        // kolom Dibayar = bayar kemarin + bayar hari ini (kedua form), bukan paid saja
        $this->assertMatchesRegularExpression(
            '/<td class="num" data-pay-paid\s+title="Bayar kemarin Rp60\.000 \+ hari ini Rp40\.000"\s*>Rp100\.000<\/td>/',
            $html
        );
        // status tetap memakai diff transaksi ini (Kurang Rp60.000)
        $this->assertMatchesRegularExpression(
            '/class="badge debt"[^>]*>Kurang Rp60\.000/s',
            $html
        );
    }

    public function test_repaying_yesterday_replaces_previous_kemarin_payment(): void
    {
        $nama = 'KoreksiKmr_'.uniqid();
        $penjualan = Penjualan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'total_kotor' => 50000,
            'total_dibulatkan' => 50000, 'dibayar' => 0, 'selisih' => 50000,
        ]);
        BukuPelanggan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'jumlah' => 50000,
            'jenis' => 'tambah', 'catatan' => 'Transaksi', 'penjualan_id' => $penjualan->id,
        ]);

        // bayar kemarin 15rb
        $this->post('/pos/transaksi/'.$penjualan->id.'/bayar', [
            'pakai_kemarin' => 1, 'bayar_kemarin' => '15.000', 'bayar_hari_ini' => '0',
        ])->assertSessionHasNoErrors();

        $penjualan->refresh();
        $this->assertSame(15000, $penjualan->dibayar_kemarin);

        // koreksi: ganti jadi 17rb → input terakhir MENANG (bukan 15k+17k = 32k)
        $this->post('/pos/transaksi/'.$penjualan->id.'/bayar', [
            'pakai_kemarin' => 1, 'bayar_kemarin' => '17.000', 'bayar_hari_ini' => '0',
        ])->assertSessionHasNoErrors();

        $penjualan->refresh();
        $this->assertSame(17000, $penjualan->dibayar_kemarin, 'Koreksi bayar kemarin harus menggantikan, bukan menumpuk.');

        // Pembayaran tidak menambah entri "bayar"; entri data uji "tambah" tidak tersentuh.
        $this->assertSame(0, BukuPelanggan::where('penjualan_id', $penjualan->id)
            ->where('jenis', 'bayar')->count());
        $this->assertSame(1, BukuPelanggan::where('penjualan_id', $penjualan->id)
            ->where('jenis', 'tambah')->count());
    }

    public function test_unchecked_kemarin_moves_money_to_paid(): void
    {
        $penjualan = Penjualan::create([
            'tanggal' => '2026-09-30', 'nama' => 'KmrOff_'.uniqid(),
            'total_kotor' => 50000, 'total_dibulatkan' => 50000,
            'dibayar' => 0, 'dibayar_kemarin' => 25000, 'selisih' => 50000,
        ]);
        BukuPelanggan::create([
            'tanggal' => '2026-09-30', 'nama' => $penjualan->nama, 'jumlah' => 25000,
            'jenis' => 'bayar', 'catatan' => 'Bayar kemarin', 'penjualan_id' => $penjualan->id,
        ]);

        // ceklis OFF di modal → paid_kemarin dinonaktifkan (0); client sudah memindahkan
        // uangnya ke form "bayar hari ini" (10rb + 25rb = 35rb) → uang tidak hilang
        $this->post('/pos/transaksi/'.$penjualan->id.'/bayar', [
            'pakai_kemarin' => 0, 'bayar_hari_ini' => '35.000',
        ])->assertSessionHasNoErrors();

        $penjualan->refresh();
        $this->assertSame(0, $penjualan->dibayar_kemarin);
        $this->assertSame(35000, $penjualan->dibayar);
        $this->assertSame(15000, $penjualan->selisih);
        // ledger manual lama tidak disentuh transaksi
        $this->assertSame(1, BukuPelanggan::where('penjualan_id', $penjualan->id)
            ->where('catatan', 'Bayar kemarin')->count());

        // badge "Bayar kemarin" hilang dari render (per baris sale ini)
        $html = $this->get('/pos?tab=transaksi&tx_date=2026-09-30')->getContent();
        $baris = $this->rowOfSale($html, $penjualan->nama);
        $this->assertStringNotContainsString('Bayar kemarin '.rupiah(25000), $baris);
        $this->assertStringContainsString('Bayar kemarin '.rupiah(0), $baris);
    }

    public function test_toggle_kemarin_off_recalculates_diff_without_moving_money(): void
    {
        $nama = 'TglKmr_'.uniqid();
        $penjualan = Penjualan::create([
            'tanggal' => '2026-10-01', 'nama' => $nama, 'total_kotor' => 200000,
            'total_dibulatkan' => 200000, 'dibayar' => 20000, 'dibayar_kemarin' => 150000,
            'kemarin_aktif' => true, 'selisih' => 180000,
        ]);

        // OFF: hanya toggle flag + hitung ulang diff — TANPA memindahkan uang
        $res = $this->postJson('/pos/transaksi/'.$penjualan->id.'/toggle-kemarin', ['active' => 0]);
        $res->assertOk()->assertJson(['ok' => true, 'paid' => 20000, 'paid_kemarin' => 150000, 'diff' => 30000]);

        $penjualan->refresh();
        $this->assertSame(20000, $penjualan->dibayar, 'paid tidak berubah (uang tidak dipindahkan).');
        $this->assertSame(150000, $penjualan->dibayar_kemarin, 'paid_kemarin tidak berubah.');
        $this->assertFalse($penjualan->kemarin_aktif);
        $this->assertSame(30000, $penjualan->selisih, 'diff = 200000 - 20000 - 150000.');
        // tombol Bayar tidak berubah; ledger tidak disentuh
        $this->assertFalse($penjualan->tombol_bayar_diklik);
        $this->assertSame(0, BukuPelanggan::where('penjualan_id', $penjualan->id)->count());

        // ON lagi: flag kembali, diff kembali — simetris
        $this->postJson('/pos/transaksi/'.$penjualan->id.'/toggle-kemarin', ['active' => 1]);
        $penjualan->refresh();
        $this->assertTrue($penjualan->kemarin_aktif);
        $this->assertSame(180000, $penjualan->selisih, 'diff = 200000 - 20000 (paid_kemarin tidak dihitung).');
        $this->assertSame(20000, $penjualan->dibayar);
        $this->assertSame(150000, $penjualan->dibayar_kemarin);

        // render ulang: badge tampil lagi di baris ini
        $html = $this->get('/pos?tab=transaksi&tx_date=2026-10-01')->getContent();
        $baris = $this->rowOfSale($html, $nama);
        $this->assertStringContainsString('Bayar kemarin '.rupiah(150000), $baris);
    }

    public function test_toggle_kemarin_on_off_is_idempotent(): void
    {
        $penjualan = Penjualan::create([
            'tanggal' => '2026-10-01', 'nama' => 'TglOn_'.uniqid(),
            'total_kotor' => 100000, 'total_dibulatkan' => 100000,
            'dibayar' => 0, 'dibayar_kemarin' => 0, 'selisih' => 100000,
        ]);

        // ON: set kemarin_active = true, diff tidak berubah (paid_kemarin = 0)
        $this->postJson('/pos/transaksi/'.$penjualan->id.'/toggle-kemarin', ['active' => 1])
            ->assertOk()->assertJson(['paid' => 0, 'paid_kemarin' => 0, 'diff' => 100000]);
        $this->assertTrue($penjualan->refresh()->kemarin_aktif);

        // OFF: set kemarin_active = false, diff tidak berubah (paid_kemarin = 0)
        $this->postJson('/pos/transaksi/'.$penjualan->id.'/toggle-kemarin', ['active' => 0])
            ->assertOk()->assertJson(['paid' => 0, 'paid_kemarin' => 0, 'diff' => 100000]);
        $penjualan->refresh();
        $this->assertFalse($penjualan->kemarin_aktif);
        $this->assertSame(0, $penjualan->dibayar);
        $this->assertSame(100000, $penjualan->selisih);
    }

    public function test_bayar_lunas_keeps_paid_kemarin(): void
    {
        $nama = 'LunasKmr_'.uniqid();
        $penjualan = Penjualan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'total_kotor' => 150000,
            'total_dibulatkan' => 150000, 'dibayar' => 0, 'dibayar_kemarin' => 50000, 'selisih' => 150000,
        ]);
        BukuPelanggan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'jumlah' => 150000,
            'jenis' => 'tambah', 'catatan' => 'Transaksi', 'penjualan_id' => $penjualan->id,
        ]);
        BukuPelanggan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'jumlah' => 50000,
            'jenis' => 'bayar', 'catatan' => 'Bayar kemarin', 'penjualan_id' => $penjualan->id,
        ]);

        $this->post('/pos/transaksi/'.$penjualan->id.'/bayar', ['lunas' => 1])
            ->assertSessionHasNoErrors();

        $penjualan->refresh();
        $this->assertSame(150000, $penjualan->dibayar);
        $this->assertSame(0, $penjualan->selisih);
        $this->assertSame(50000, $penjualan->dibayar_kemarin);

        // Pembayaran lunas juga tidak menambah entri "bayar"; entri data uji
        // "Bayar kemarin" tetap dan tidak ada entri "Pembayaran lunas transaksi" baru.
        $bayar = BukuPelanggan::where('penjualan_id', $penjualan->id)->where('jenis', 'bayar')->pluck('jumlah', 'catatan');
        $this->assertSame(50000, $bayar['Bayar kemarin']);
        $this->assertArrayNotHasKey('Pembayaran lunas transaksi', $bayar);
    }

    public function test_bayar_lunas_is_rejected_for_payment_only_row(): void
    {
        $nama = 'LunasNol_'.uniqid();
        $penjualan = Penjualan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'total_kotor' => 0,
            'total_dibulatkan' => 0, 'dibayar' => 30000, 'selisih' => -30000,
        ]);

        $this->post('/pos/transaksi/'.$penjualan->id.'/bayar', ['lunas' => 1])
            ->assertSessionHas('error');

        $penjualan->refresh();
        $this->assertSame(30000, $penjualan->dibayar, 'Paid tidak boleh tertimpa jadi 0.');
        $this->assertSame(0, BukuPelanggan::where('penjualan_id', $penjualan->id)
            ->where('catatan', 'Pembayaran lunas transaksi')->count());
    }

    public function test_load_for_payment_keeps_paid_for_payment_only_row(): void
    {
        $penjualan = Penjualan::create([
            'tanggal' => '2026-09-30', 'nama' => 'EditBayar_'.uniqid(),
            'total_kotor' => 0, 'total_dibulatkan' => 0, 'dibayar' => 45000, 'selisih' => -45000,
        ]);

        $respons = $this->post('/pos/transaksi/'.$penjualan->id.'/load-for-payment');

        $location = (string) $respons->headers->get('Location');
        $this->assertStringContainsString('tx_paid=45000', $location);
    }

    public function test_load_for_payment_prefills_current_paid_for_edit(): void
    {
        // edit baris tagihan menampilkan paid sekarang (input terakhir menang saat disimpan)
        $penjualan = Penjualan::create([
            'tanggal' => '2026-09-30', 'nama' => 'EditTagihan_'.uniqid(),
            'total_kotor' => 50000, 'total_dibulatkan' => 50000, 'dibayar' => 15000, 'selisih' => 35000,
        ]);

        $respons = $this->post('/pos/transaksi/'.$penjualan->id.'/load-for-payment');

        $location = (string) $respons->headers->get('Location');
        $this->assertStringContainsString('tx_paid=15000', $location);
    }

    public function test_pay_payload_has_no_prefill(): void
    {
        $nama = 'Prefill_'.uniqid();
        Penjualan::create([
            'tanggal' => '2026-09-29', 'nama' => $nama, 'total_kotor' => 180000,
            'total_dibulatkan' => 180000, 'dibayar' => 80000, 'selisih' => 100000,
        ]);
        Penjualan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'total_kotor' => 150000,
            'total_dibulatkan' => 150000, 'dibayar' => 0, 'selisih' => 150000,
        ]);

        $html = $this->get('/pos?tab=transaksi&tx_date=2026-09-30')->getContent();

        // Payload modal tidak membawa prefill_kemarin; field bayar hari ini
        // default 0. Ceklis "Bayar kemarin" di-seed dari data agar tetap ON
        // setelah reload bila paid_kemarin > 0.
        $this->assertStringNotContainsString('"prefill_kemarin"', $html);
        $this->assertStringContainsString('window.POS_PAY', $html);

        $js = (string) file_get_contents(base_path('resources/js/pos/pembayaran.js'));
        // Ceklis "Bayar kemarin" di-seed per baris dari kolom kemarin_active.
        $this->assertStringContainsString('keadaan.kemarin[id] = !!barisPembayaran[id].kemarin_active', $js);
        $this->assertStringContainsString('String(s.paid_kemarin)', $js);
    }

    public function test_pay_payload_escapes_html_in_customer_name(): void
    {
        $nama = '<script>alert(1)</script>'.uniqid();
        Penjualan::create([
            'tanggal' => now()->toDateString(), 'nama' => $nama,
            'total_kotor' => 0, 'total_dibulatkan' => 0, 'dibayar' => 0, 'selisih' => 0,
        ]);

        try {
            $html = $this->get('/pos?tab=transaksi')->getContent();

            $this->assertStringContainsString('window.POS_PAY', $html);
            // Nama pelanggan di-escape oleh Blade dan payload JSON dibuat aman.
            $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
            $this->assertStringNotContainsString($nama, $html);
        } finally {
            Penjualan::where('nama', $nama)->delete();
        }
    }

    public function test_recap_diterima_includes_paid_kemarin(): void
    {
        $nama = 'RecapPay_'.uniqid();
        Penjualan::create([
            'tanggal' => now()->toDateString(), 'nama' => $nama, 'total_kotor' => 0,
            'total_dibulatkan' => 0, 'dibayar' => 20777, 'dibayar_kemarin' => 13333, 'selisih' => -20777,
        ]);

        $html = $this->get('/pos')->getContent();

        // "Diterima" = sum(paid) + sum(paid_kemarin) → uang kemarin ikut dihitung hari ini
        $salesToday = Penjualan::where('tanggal', now()->toDateString())->get();
        $diharapkan = rupiah((int) $salesToday->sum('dibayar') + (int) $salesToday->sum('dibayar_kemarin'));

        $this->assertMatchesRegularExpression(
            '/Diterima<\/span>\s*<span class="num green">'.preg_quote($diharapkan, '/').'<\/span>/',
            $html
        );
        // pembayaran kemarin tidak disembunyikan dari kolom Dibayar barisnya
        $this->assertStringContainsString('Bayar kemarin '.rupiah(13333), $html);
    }

    public function test_editing_sale_does_not_touch_ledger(): void
    {
        $produk = Produk::create(['nama' => 'UjiEdit_'.uniqid(), 'harga' => 10000]);
        $nama = 'EditLedger_'.uniqid();
        $penjualan = Penjualan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'total_kotor' => 150000,
            'total_dibulatkan' => 150000, 'dibayar' => 0, 'dibayar_kemarin' => 60000, 'selisih' => 150000,
        ]);
        BukuPelanggan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'jumlah' => 150000,
            'jenis' => 'tambah', 'catatan' => 'Transaksi', 'penjualan_id' => $penjualan->id,
        ]);
        BukuPelanggan::create([
            'tanggal' => '2026-09-30', 'nama' => $nama, 'jumlah' => 60000,
            'jenis' => 'bayar', 'catatan' => 'Bayar kemarin', 'penjualan_id' => $penjualan->id,
        ]);

        // edit: ganti jumlah produk lalu simpan ulang
        $this->post('/pos/transaksi', [
            'editing_sale_id' => $penjualan->id,
            'txName' => $nama,
            'txDate' => '2026-09-30',
            'qty' => [$produk->id => '2'],
            'tx_paid_touched' => 0,
        ])->assertSessionHasNoErrors();

        $penjualan->refresh();
        $this->assertSame(60000, $penjualan->dibayar_kemarin, 'paid_kemarin tidak boleh hilang saat edit.');
        $this->assertSame(20000, $penjualan->total_dibulatkan);

        // edit TIDAK menghapus/membuat entri ledger — riwayat lama tetap utuh
        $this->assertSame(1, BukuPelanggan::where('penjualan_id', $penjualan->id)->where('jenis', 'tambah')->count());
        $bayarKemarin = BukuPelanggan::where('penjualan_id', $penjualan->id)
            ->where('jenis', 'bayar')->where('catatan', 'Bayar kemarin')->get();
        $this->assertCount(1, $bayarKemarin);
        $this->assertSame(60000, $bayarKemarin[0]->jumlah);

        $produk->delete();
    }

    public function test_pay_button_green_when_unpaid_and_gray_when_paid(): void
    {
        $unpaid = 'BtnGreen_'.uniqid();
        $dibayar = 'BtnGray_'.uniqid();
        Penjualan::create(['tanggal' => now()->toDateString(), 'nama' => $unpaid, 'total_kotor' => 0,
            'total_dibulatkan' => 0, 'dibayar' => 0, 'selisih' => 0, 'tombol_bayar_diklik' => false]);
        Penjualan::create(['tanggal' => now()->toDateString(), 'nama' => $dibayar, 'total_kotor' => 0,
            'total_dibulatkan' => 0, 'dibayar' => 0, 'selisih' => 0, 'tombol_bayar_diklik' => true]);

        $html = $this->get('/pos?tab=transaksi')->getContent();

        // belum dibayar → hijau (btn-success), sudah pernah dibayar → abu (btn-outline-secondary)
        $this->assertMatchesRegularExpression(
            '/class="btn btn-success"[^>]*title="Buka modal pembayaran '.preg_quote($unpaid, '/').'"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/class="btn btn-outline-secondary"[^>]*title="Buka modal pembayaran '.preg_quote($dibayar, '/').'"/',
            $html
        );
    }

    public function test_pos_root_uses_bootstrap_modal_without_alpine(): void
    {
        // Modal Bayar memakai Bootstrap 5 (window.bootstrap.Modal) dan JavaScript vanilla.
        $html = $this->get('/pos')->getContent();

        $this->assertStringContainsString('id="pos-root"', $html);
        $this->assertStringNotContainsString('x-data', $html);
        $this->assertStringNotContainsString('$store.pay', $html);
        $this->assertStringContainsString('id="payModal"', $html);
        $this->assertStringContainsString('class="modal fade" id="payModal"', $html);
        $this->assertStringContainsString('window.POS_PAY', $html);
        // Payload modal tidak memuat prefill_kemarin.
        $this->assertStringNotContainsString('prefill_kemarin', $html);
    }

    public function test_modal_markup_and_riwayat_removed_from_table(): void
    {
        Penjualan::create([
            'tanggal' => now()->toDateString(), 'nama' => 'Markup_'.uniqid(),
            'total_kotor' => 100000, 'total_dibulatkan' => 100000, 'dibayar' => 0, 'selisih' => 100000,
        ]);

        $html = $this->get('/pos?tab=transaksi')->getContent();

        $this->assertStringContainsString('window.POS_PAY', $html);
        $this->assertStringContainsString('id="payModal"', $html);
        $this->assertStringContainsString('data-pay-open=', $html);
        $this->assertStringContainsString('Bayar kemarin', $html);
        // pembayaran.js dimuat sebagai module Vite untuk mode dev (hot) maupun build.
        $this->assertMatchesRegularExpression('/<script[^>]+type="module"[^>]+src="[^"]*app[^"]*\.js"/', $html);

        // Modal tidak menampilkan total semua pelanggan atau tombol Tutup;
        // penutupan dilakukan lewat tombol X.
        $this->assertStringNotContainsString('Total hutang semua pelanggan', $html);
        $this->assertStringNotContainsString('$store.pay.close()', $html);

        // Tombol Riwayat tidak ditampilkan di tabel transaksi maupun tab Hasil;
        // detail hanya dapat dibuka lewat URL ?hp_detail=Nama.
        $start = strpos($html, 'id="tab-transaksi"');
        $end = strpos($html, 'id="tab-produk"');
        $slice = substr($html, $start, $end - $start);
        $this->assertStringNotContainsString('hp_detail', $slice);
        $this->assertStringNotContainsString('hp_detail', substr($html, $end));
    }
}
